<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentIssued
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $documentId,
        public string|int|null $chatId,
        public ?int $userId,
    ) {}
}
