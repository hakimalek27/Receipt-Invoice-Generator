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
            'Confirm: /confirm '.$token['token'],
            'Expires: '.$token['expires_at'],
        ];

        return $this->sendMessage(
            $chatId,
            implode("\n", $lines),
            $document->company_id,
            $userId,
            $document->id,
            $token['token']
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
        ?string $secretFragment = null
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

        try {
            $response = Http::asJson()
                ->timeout(10)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'disable_web_page_preview' => true,
                ])
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

    private function redact(string $value, ?string $secretFragment = null): string
    {
        $value = preg_replace('/\/confirm\s+[A-Za-z0-9]+/', '/confirm [redacted-token]', $value) ?? $value;
        if ($secretFragment) {
            $value = str_replace($secretFragment, '[redacted-token]', $value);
        }

        return $value;
    }
}
