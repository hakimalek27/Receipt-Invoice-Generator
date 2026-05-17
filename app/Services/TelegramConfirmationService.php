<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Str;

class TelegramConfirmationService
{
    /**
     * Telegram chat_id allowlist. In production, this comes from config/database.
     */
    private array $allowedChatIds = [];

    public function __construct()
    {
        $this->allowedChatIds = array_filter(
            explode(',', config('services.telegram.allowed_chat_ids', ''))
        );
    }

    /**
     * Verify a Telegram chat_id is authorized.
     */
    public function isAuthorized(string $chatId): bool
    {
        if (empty($this->allowedChatIds)) {
            return false;
        }
        return in_array($chatId, $this->allowedChatIds, true);
    }

    /**
     * Generate a confirmation token for a draft document.
     */
    public function generateToken(Document $document, User $user, string $chatId): array
    {
        $token = Str::random(64);
        $expiresAt = now()->addMinutes(30);

        return [
            'token' => $token,
            'draft_hash' => $document->draft_hash,
            'company_id' => $document->company_id,
            'user_id' => $user->id,
            'chat_id' => $chatId,
            'expires_at' => $expiresAt->toIso8601String(),
            'idempotency_key' => 'tg_' . Str::uuid(),
            'document_id' => $document->id,
        ];
    }

    /**
     * Validate a confirmation token.
     */
    public function validateToken(array $token, string $currentDraftHash): bool
    {
        // Check expiry
        if (isset($token['expires_at'])) {
            $expiresAt = \Carbon\Carbon::parse($token['expires_at']);
            if ($expiresAt->isPast()) {
                return false;
            }
        }

        // Check draft hasn't changed
        if ($token['draft_hash'] !== $currentDraftHash) {
            return false;
        }

        return true;
    }
}
