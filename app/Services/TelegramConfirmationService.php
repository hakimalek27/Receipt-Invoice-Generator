<?php

namespace App\Services;

use App\Models\Document;
use App\Models\TelegramConfirmationToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TelegramConfirmationService
{
    private array $allowedChatIds = [];

    private array $chatUserMap = [];

    public function __construct()
    {
        $this->allowedChatIds = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) config('services.telegram.allowed_chat_ids', ''))
        )));

        $this->chatUserMap = $this->parseChatUserMap((string) config('services.telegram.chat_user_map', ''));
    }

    public function isAuthorized(string $chatId): bool
    {
        return $this->allowedChatIds !== [] && in_array($chatId, $this->allowedChatIds, true);
    }

    public function userIdForChat(string $chatId): ?int
    {
        return $this->chatUserMap[$chatId] ?? null;
    }

    public function generateToken(Document $document, User $user, string $chatId, ?string $telegramUserId = null): array
    {
        $plainToken = Str::random(64);
        $expiresAt = now()->addMinutes((int) config('services.telegram.confirmation_ttl_minutes', 30));
        $idempotencyKey = 'tg_'.(string) Str::uuid();

        $record = TelegramConfirmationToken::create([
            'company_id' => $document->company_id,
            'user_id' => $user->id,
            'document_id' => $document->id,
            'chat_id' => $chatId,
            'telegram_user_id' => $telegramUserId,
            'token_hash' => $this->hashToken($plainToken),
            'draft_hash' => (string) $document->draft_hash,
            'idempotency_key' => $idempotencyKey,
            'expires_at' => $expiresAt,
            'metadata' => [
                'source' => 'telegram',
                'company_id' => $document->company_id,
                'document_type' => $document->document_type,
            ],
        ]);

        return [
            'id' => $record->id,
            'token' => $plainToken,
            'draft_hash' => $record->draft_hash,
            'company_id' => $record->company_id,
            'user_id' => $record->user_id,
            'chat_id' => $record->chat_id,
            'telegram_user_id' => $record->telegram_user_id,
            'expires_at' => $record->expires_at->toIso8601String(),
            'idempotency_key' => $record->idempotency_key,
            'document_id' => $record->document_id,
        ];
    }

    public function validateToken(array $token, string $currentDraftHash): bool
    {
        if (($token['draft_hash'] ?? null) !== $currentDraftHash) {
            return false;
        }

        if (! empty($token['expires_at']) && now()->greaterThanOrEqualTo(\Carbon\Carbon::parse($token['expires_at']))) {
            return false;
        }

        return true;
    }

    public function consumeForIssue(string $plainToken, string $chatId, ?string $telegramUserId): ?TelegramConfirmationToken
    {
        return DB::transaction(function () use ($plainToken, $chatId, $telegramUserId) {
            $record = TelegramConfirmationToken::query()
                ->where('token_hash', $this->hashToken($plainToken))
                ->lockForUpdate()
                ->first();

            if (! $record || $record->used_at || now()->greaterThanOrEqualTo($record->expires_at)) {
                return null;
            }

            if ($record->chat_id !== $chatId) {
                return null;
            }

            if ($record->telegram_user_id && $telegramUserId && $record->telegram_user_id !== $telegramUserId) {
                return null;
            }

            $document = Document::with('items')->lockForUpdate()->find($record->document_id);
            if (! $document || ! $document->isDraft() || ! hash_equals($record->draft_hash, (string) $document->draft_hash)) {
                return null;
            }

            $record->update(['used_at' => now()]);

            return $record->fresh(['document', 'user']);
        });
    }

    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function parseChatUserMap(string $map): array
    {
        $result = [];
        foreach (array_filter(array_map('trim', explode(',', $map))) as $entry) {
            [$chatId, $userId] = array_pad(explode(':', $entry, 2), 2, null);
            if ($chatId && $userId && ctype_digit($userId)) {
                $result[$chatId] = (int) $userId;
            }
        }

        return $result;
    }
}
