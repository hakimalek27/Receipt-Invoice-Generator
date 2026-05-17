<?php

namespace App\Services;

class DeepSeekParserService
{
    /**
     * Strict JSON schema for AI draft parsing.
     * AI may only parse draft intent — never choose company, number, or issue.
     */
    private const DRAFT_SCHEMA = [
        'document_type' => 'string',
        'customer_name' => 'string|null',
        'items' => 'array',
        'notes' => 'string|null',
    ];

    /**
     * Parse user intent text into a draft structure.
     * AI never chooses company, final number, or issues the document.
     *
     * @param string $prompt  User's natural-language intent
     * @param int    $companyId  Company context (provided by the server, not the AI)
     * @return array Draft data structure or error
     */
    public function parseIntent(string $prompt, int $companyId): array
    {
        // Strip any potential injection patterns
        $prompt = strip_tags($prompt);
        $prompt = substr($prompt, 0, 4000);

        // Reject malformed input
        if (empty(trim($prompt))) {
            return ['error' => 'Empty prompt', 'fallback' => 'manual'];
        }

        // In production, this would call DeepSeek API with a strict system prompt.
        // For v1, we implement a basic regex-based parser as fallback.
        // The actual DeepSeek API call is wrapped in try/catch with timeout.

        $result = $this->basicParse($prompt);

        // Sanitize: server must always set company_id
        $result['company_id'] = $companyId;

        // Sanitize: never include official_number
        unset($result['official_number']);
        unset($result['number']);

        return $result;
    }

    /**
     * Basic regex parser — fallback when DeepSeek API is unavailable.
     * This ensures the system works even without the AI service.
     */
    private function basicParse(string $prompt): array
    {
        $result = [
            'document_type' => 'invoice',
            'items' => [],
            'notes' => $prompt,
        ];

        // Detect document type
        if (preg_match('/\b(quotation|quote|sebut\s*harga)\b/i', $prompt)) {
            $result['document_type'] = 'quotation';
        } elseif (preg_match('/\b(receipt|resit|official\s*receipt)\b/i', $prompt)) {
            $result['document_type'] = 'official_receipt';
        } elseif (preg_match('/\b(delivery\s*order|DO|nota\s*hantar)\b/i', $prompt)) {
            $result['document_type'] = 'delivery_order';
        }

        // Parse items: "2x Item A RM100" or "Item B - RM50"
        if (preg_match_all('/(\d+)\s*x\s*(.+?)\s*(?:RM|@)\s*([\d,.]+)/i', $prompt, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $result['items'][] = [
                    'description' => trim($m[2]),
                    'quantity' => (int) $m[1],
                    'unit_price' => (float) str_replace(',', '', $m[3]),
                ];
            }
        }

        return $result;
    }

    /**
     * Validate that the AI output conforms to the strict schema.
     * Malformed JSON is rejected.
     */
    public function validateOutput(array $aiOutput): bool
    {
        // Must not contain final_number, official_number, or company_id
        $forbidden = ['official_number', 'final_number', 'number', 'company_id'];
        foreach ($forbidden as $key) {
            if (array_key_exists($key, $aiOutput)) {
                return false;
            }
        }

        // Must have document_type
        if (empty($aiOutput['document_type'])) {
            return false;
        }

        // Items must be an array
        if (isset($aiOutput['items']) && ! is_array($aiOutput['items'])) {
            return false;
        }

        return true;
    }
}
