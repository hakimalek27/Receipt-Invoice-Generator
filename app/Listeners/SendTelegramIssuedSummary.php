<?php

namespace App\Listeners;

use App\Events\DocumentIssued;
use App\Models\Document;
use App\Services\TelegramOutboundService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Storage;

class SendTelegramIssuedSummary implements ShouldQueue
{
    public bool $afterCommit = true;

    public function __construct(
        private readonly TelegramOutboundService $outbound,
    ) {}

    public function handle(DocumentIssued $event): void
    {
        $document = Document::with('company', 'customer', 'pdfRenders')->find($event->documentId);
        if (! $document || ! $event->chatId) {
            return;
        }

        $this->outbound->sendIssuedSummary($document, (string) $event->chatId, $event->userId ?? 0);

        $current = $document->pdfRenders()->where('is_current', true)->latest()->first()
            ?? $document->pdfRenders()->latest()->first();
        if (! $current) {
            return;
        }

        $absPath = Storage::disk('local')->path($current->file_path);
        $caption = sprintf(
            'Issued: %s · Total %s %s',
            $document->official_number ?? '(no number)',
            $document->currency,
            number_format((float) $document->grand_total, 2),
        );

        $this->outbound->sendDocument(
            (string) $event->chatId,
            $absPath,
            $caption,
            $document->company_id,
            $event->userId,
            $document->id,
            null,
            null
        );
    }
}
