<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DeepSeekParserService
{
    private const FORBIDDEN_KEYS = ['official_number', 'final_number', 'number', 'company_id', 'issue', 'issued'];

    private const DOCUMENT_TYPES = [
        'invoice',
        'quotation',
        'official_receipt',
        'delivery_order',
        'cash_bill',
        'credit_note',
        'debit_note',
        'purchase_order',
        'payment_voucher',
        'proforma_invoice',
    ];

    public function parseIntent(string $prompt, int $companyId): array
    {
        $prompt = $this->sanitizeText($prompt, 4000);
        if (trim($prompt) === '') {
            return [
                'error' => 'Empty prompt',
                'fallback' => 'manual',
                'company_id' => $companyId,
            ];
        }

        $status = 'fallback_regex';
        $payload = null;

        if ((string) config('services.deepseek.api_key') !== '') {
            try {
                $payload = $this->callDeepSeek($prompt);
                $status = 'deepseek';
            } catch (\Throwable $exception) {
                Log::warning('deepseek.parse_failed', [
                    'status' => 'fallback_regex',
                    'error_class' => $exception::class,
                    'retention' => config('services.deepseek.retention_mode', 'redacted'),
                ]);
            }
        }

        if (! $payload || ! $this->validateOutput($payload)) {
            $payload = $this->basicParse($prompt);
            $status = 'fallback_regex';
        }

        $result = $this->normalizeDraftPayload($payload, $companyId);
        $result['ai_status'] = $status;

        Log::info('deepseek.parse_completed', [
            'ai_status' => $status,
            'document_type' => $result['document_type'],
            'item_count' => count($result['items'] ?? []),
            'retention' => config('services.deepseek.retention_mode', 'redacted'),
        ]);

        return $result;
    }

    public function validateOutput(array $aiOutput): bool
    {
        foreach (self::FORBIDDEN_KEYS as $key) {
            if (array_key_exists($key, $aiOutput)) {
                return false;
            }
        }

        if (empty($aiOutput['document_type']) || ! in_array($aiOutput['document_type'], self::DOCUMENT_TYPES, true)) {
            return false;
        }

        if (! isset($aiOutput['items']) || ! is_array($aiOutput['items'])) {
            return false;
        }

        foreach ($aiOutput['items'] as $item) {
            if (! is_array($item) || empty($item['description'])) {
                return false;
            }
            if (isset($item['quantity']) && (! is_numeric($item['quantity']) || (float) $item['quantity'] <= 0)) {
                return false;
            }
            if (isset($item['unit_price']) && (! is_numeric($item['unit_price']) || (float) $item['unit_price'] < 0)) {
                return false;
            }
        }

        return true;
    }

    private function callDeepSeek(string $prompt): ?array
    {
        $baseUrl = rtrim((string) config('services.deepseek.base_url'), '/');
        $timeout = (int) config('services.deepseek.timeout', 20);
        $retries = (int) config('services.deepseek.retries', 1);

        $response = Http::withToken((string) config('services.deepseek.api_key'))
            ->acceptJson()
            ->timeout($timeout)
            ->retry($retries, 250)
            ->post($baseUrl.'/chat/completions', [
                'model' => config('services.deepseek.model', 'deepseek-chat'),
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => implode(' ', [
                            'Return JSON only for a draft business document.',
                            'Allowed keys: document_type, customer_name, items, notes.',
                            'Allowed document_type values: '.implode(', ', self::DOCUMENT_TYPES).'.',
                            'Never set company_id, official_number, final_number, number, issue, or issued.',
                            'Items must contain description, quantity, unit_price, optional discount, uom.',
                        ]),
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ])
            ->throw();

        $content = data_get($response->json(), 'choices.0.message.content');
        if (! is_string($content)) {
            return null;
        }

        return $this->decodeJsonContent($content);
    }

    private function decodeJsonContent(string $content): ?array
    {
        $content = trim($content);
        if (Str::startsWith($content, '```')) {
            $content = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $content) ?? $content;
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function basicParse(string $prompt): array
    {
        $result = [
            'document_type' => 'invoice',
            'items' => [],
            'notes' => $prompt,
        ];

        if (preg_match('/\b(quotation|quote|sebut\s*harga)\b/i', $prompt)) {
            $result['document_type'] = 'quotation';
        } elseif (preg_match('/\b(receipt|resit|official\s*receipt)\b/i', $prompt)) {
            $result['document_type'] = 'official_receipt';
        } elseif (preg_match('/\b(delivery\s*order|nota\s*hantar)\b/i', $prompt)) {
            $result['document_type'] = 'delivery_order';
        }

        if (preg_match_all('/(\d+(?:\.\d+)?)\s*x\s*(.+?)\s*(?:RM|@)\s*([\d,.]+)/i', $prompt, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $result['items'][] = [
                    'description' => $this->sanitizeText($match[2], 500),
                    'quantity' => (float) $match[1],
                    'unit_price' => (float) str_replace(',', '', $match[3]),
                ];
            }
        }

        return $result;
    }

    private function normalizeDraftPayload(array $payload, int $companyId): array
    {
        foreach (self::FORBIDDEN_KEYS as $key) {
            unset($payload[$key]);
        }

        $documentType = $payload['document_type'] ?? 'invoice';
        if (! in_array($documentType, self::DOCUMENT_TYPES, true)) {
            $documentType = 'invoice';
        }

        $items = [];
        foreach (($payload['items'] ?? []) as $item) {
            if (! is_array($item) || empty($item['description'])) {
                continue;
            }
            $items[] = [
                'description' => $this->sanitizeText((string) $item['description'], 500),
                'quantity' => max(0.0001, (float) ($item['quantity'] ?? 1)),
                'unit_price' => max(0, (float) ($item['unit_price'] ?? 0)),
                'discount' => max(0, (float) ($item['discount'] ?? 0)),
                'uom' => $this->sanitizeText((string) ($item['uom'] ?? 'unit'), 50),
            ];
        }

        return [
            'document_type' => $documentType,
            'customer_name' => isset($payload['customer_name'])
                ? $this->sanitizeText((string) $payload['customer_name'], 255)
                : null,
            'items' => $items,
            'notes' => isset($payload['notes'])
                ? $this->sanitizeText((string) $payload['notes'], 1000)
                : null,
            'company_id' => $companyId,
        ];
    }

    private function sanitizeText(string $value, int $maxLength): string
    {
        return Str::limit(trim(strip_tags($value)), $maxLength, '');
    }
}
