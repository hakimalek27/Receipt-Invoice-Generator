<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentDrafted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $documentId,
        public int $tokenId,
        public string $tokenPlain,
        public string|int|null $chatId,
        public ?int $userId,
    ) {}
}
