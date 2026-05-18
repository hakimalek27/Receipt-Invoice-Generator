<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentRejected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $documentId,
        public string|int|null $chatId,
        public ?int $userId,
    ) {}
}
