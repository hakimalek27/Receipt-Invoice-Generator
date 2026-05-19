<?php

namespace App\Services;

use App\Models\Document;
use App\Models\TelegramMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TelegramOutboundService
{
    public function recordInbound(
        ?string $chatId,
        ?string $telegramUserId,
        string $text,
        string $status = 'received',
        ?int $companyId = null,
        ?int $userId = null,
        ?int $documentId = null,
        ?string $error = null
    ): TelegramMessage {
        return TelegramMessage::create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'document_id' => $documentId,
            'chat_id' => $chatId,
            'telegram_user_id' => $telegramUserId,
            'direction' => 'inbound',
            'payload_redacted' => [
                'text' => $this->redact((string) Str::limit($text, 1000, '')),
            ],
            'status' => $status,
            'error' => $error,
        ]);
    }

    public function sendDraftSummary(Document $document, array $token, string $chatId, int $userId): TelegramMessage
    {
        $document->loadMissing('items', 'customer');

        $lines = [
            'Draft created. Confirmation required.',
            'Document: '.str_replace('_', ' ', $document->document_type).' #'.$document->id,
            'Customer: '.($document->customer?->name ?? '-'),
            'Items: '.$document->items->count(),
            'Total: '.$document->currency.' '.number_format((float) $document->grand_total, 2),
            'Draft hash: '.substr((string) $document->draft_hash, 0, 12).'...',
            'Expires: '.$token['expires_at'],
        ];

        $replyMarkup = [
            'inline_keyboard' => [[
                ['text' => '✓ Approve', 'callback_data' => 'approve:'.$token['token']],
                ['text' => '✗ Reject', 'callback_data' => 'reject:'.$token['token']],
            ]],
        ];

        return $this->sendMessage(
            $chatId,
            implode("\n", $lines),
            $document->company_id,
            $userId,
            $document->id,
            $token['token'],
            $replyMarkup
        );
    }

    public function sendIssuedSummary(Document $document, string $chatId, int $userId): TelegramMessage
    {
        $url = rtrim((string) config('app.url'), '/')."/api/documents/{$document->id}/pdf";
        $text = implode("\n", [
            'Document issued.',
            'Number: '.$document->official_number,
            'Status: '.$document->status,
            'Total: '.$document->currency.' '.number_format((float) $document->grand_total, 2),
            'PDF: '.$url,
        ]);

        return $this->sendMessage($chatId, $text, $document->company_id, $userId, $document->id);
    }

    public function sendText(
        string $chatId,
        string $text,
        ?int $companyId = null,
        ?int $userId = null,
        ?int $documentId = null
    ): TelegramMessage {
        return $this->sendMessage($chatId, $text, $companyId, $userId, $documentId);
    }

    private function sendMessage(
        string $chatId,
        string $text,
        ?int $companyId,
        ?int $userId,
        ?int $documentId,
        ?string $secretFragment = null,
        ?array $replyMarkup = null
    ): TelegramMessage {
        $message = TelegramMessage::create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'document_id' => $documentId,
            'chat_id' => $chatId,
            'direction' => 'outbound',
            'payload_redacted' => [
                'method' => 'sendMessage',
                'text' => $this->redact($text, $secretFragment),
                'has_reply_markup' => $replyMarkup !== null,
            ],
            'status' => 'pending',
        ]);

        $token = (string) config('services.telegram.bot_token');
        if ($token === '') {
            $message->update([
                'status' => 'skipped',
                'error' => 'TELEGRAM_BOT_TOKEN is not configured',
            ]);

            return $message->fresh();
        }

        $body = [
            'chat_id' => $chatId,
            'text' => $text,
            'disable_web_page_preview' => true,
        ];
        if ($replyMarkup !== null) {
            $body['reply_markup'] = $replyMarkup;
        }

        try {
            $response = Http::asJson()
                ->timeout(10)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", $body)
                ->throw();

            $message->update([
                'status' => data_get($response->json(), 'ok') ? 'sent' : 'failed',
                'sent_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $message->update([
                'status' => 'failed',
                'error' => Str::limit($exception->getMessage(), 1000, ''),
            ]);
        }

        return $message->fresh();
    }

    public function sendDocument(
        string $chatId,
        string $filePath,
        string $caption,
        ?int $companyId,
        ?int $userId,
        ?int $documentId,
        ?string $secretFragment = null,
        ?array $replyMarkup = null
    ): TelegramMessage {
        $message = TelegramMessage::create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'document_id' => $documentId,
            'chat_id' => $chatId,
            'direction' => 'outbound',
            'payload_redacted' => [
                'method' => 'sendDocument',
                'document_filename' => is_file($filePath) ? basename($filePath) : '(missing)',
                'caption' => $this->redact($caption, $secretFragment),
                'has_reply_markup' => $replyMarkup !== null,
            ],
            'status' => 'pending',
        ]);

        $token = (string) config('services.telegram.bot_token');
        if ($token === '') {
            $message->update([
                'status' => 'skipped',
                'error' => 'TELEGRAM_BOT_TOKEN is not configured',
            ]);

            return $message->fresh();
        }

        if (! is_file($filePath)) {
            $message->update([
                'status' => 'failed',
                'error' => 'PDF file not found at: '.$filePath,
            ]);

            return $message->fresh();
        }

        try {
            $request = Http::attach('document', file_get_contents($filePath), basename($filePath))
                ->timeout(30);

            $form = [
                'chat_id' => $chatId,
                'caption' => $caption,
                'parse_mode' => 'HTML',
            ];
            if ($replyMarkup !== null) {
                $form['reply_markup'] = json_encode($replyMarkup, JSON_UNESCAPED_UNICODE);
            }

            $response = $request
                ->post("https://api.telegram.org/bot{$token}/sendDocument", $form)
                ->throw();

            $message->update([
                'status' => data_get($response->json(), 'ok') ? 'sent' : 'failed',
                'sent_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $message->update([
                'status' => 'failed',
                'error' => Str::limit($exception->getMessage(), 1000, ''),
            ]);
        }

        return $message->fresh();
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text = ''): void
    {
        $token = (string) config('services.telegram.bot_token');
        if ($token === '') {
            return;
        }

        try {
            Http::asJson()
                ->timeout(5)
                ->post("https://api.telegram.org/bot{$token}/answerCallbackQuery", array_filter([
                    'callback_query_id' => $callbackQueryId,
                    'text' => $text !== '' ? $text : null,
                ]));
        } catch (\Throwable $exception) {
            // Best-effort spinner dismiss; non-critical.
        }
    }

    private function redact(string $value, ?string $secretFragment = null): string
    {
        $value = preg_replace(
            '/(?:\/confirm\s+|approve:|reject:)[A-Za-z0-9]+/',
            '[redacted-token]',
            $value
        ) ?? $value;
        if ($secretFragment) {
            $value = str_replace($secretFragment, '[redacted-token]', $value);
        }

        return $value;
    }
}
