<?php

namespace App\Listeners;

use App\Events\DocumentRejected;
use App\Models\Document;
use App\Services\TelegramOutboundService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendTelegramRejectedSummary implements ShouldQueue
{
    public bool $afterCommit = true;

    public function __construct(
        private readonly TelegramOutboundService $outbound,
    ) {}

    public function handle(DocumentRejected $event): void
    {
        if (! $event->chatId) {
            return;
        }

        $document = Document::withTrashed()->find($event->documentId);
        $label = $document
            ? sprintf('%s #%d', str_replace('_', ' ', $document->document_type), $document->id)
            : 'Draft #'.$event->documentId;

        $this->outbound->sendText(
            (string) $event->chatId,
            sprintf('%s rejected and discarded.', $label),
            $document?->company_id,
            $event->userId,
            $event->documentId,
        );
    }
}
