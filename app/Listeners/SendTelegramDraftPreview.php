<?php

namespace App\Listeners;

use App\Events\DocumentDrafted;
use App\Models\Document;
use App\Services\PdfRenderService;
use App\Services\TelegramOutboundService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Storage;

class SendTelegramDraftPreview implements ShouldQueue
{
    public bool $afterCommit = true;

    public function __construct(
        private readonly PdfRenderService $pdf,
        private readonly TelegramOutboundService $outbound,
    ) {}

    public function handle(DocumentDrafted $event): void
    {
        $document = Document::with('items', 'company', 'customer')->find($event->documentId);
        if (! $document || ! $event->chatId) {
            return;
        }

        $confirmToken = \App\Models\TelegramConfirmationToken::find($event->tokenId);
        $expiresAt = $confirmToken?->expires_at?->toIso8601String() ?? '';

        $tokenArray = [
            'id' => $event->tokenId,
            'token' => $event->tokenPlain,
            'expires_at' => $expiresAt,
        ];

        $this->outbound->sendDraftSummary($document, $tokenArray, (string) $event->chatId, $event->userId ?? 0);

        $render = $this->pdf->render($document);
        $absPath = Storage::disk('local')->path($render->file_path);

        $replyMarkup = [
            'inline_keyboard' => [[
                ['text' => '✓ Approve', 'callback_data' => 'approve:'.$event->tokenPlain],
                ['text' => '✗ Reject', 'callback_data' => 'reject:'.$event->tokenPlain],
            ]],
        ];

        $caption = sprintf(
            'PDF preview · Tap a button above to approve or reject.',
        );

        $this->outbound->sendDocument(
            (string) $event->chatId,
            $absPath,
            $caption,
            $document->company_id,
            $event->userId,
            $document->id,
            $event->tokenPlain,
            $replyMarkup
        );
    }
}
