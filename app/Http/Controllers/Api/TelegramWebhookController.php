<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DeepSeekParserService;
use App\Services\DocumentWorkflowService;
use App\Services\TelegramConfirmationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __construct(
        private readonly DeepSeekParserService $parser,
        private readonly DocumentWorkflowService $workflow,
        private readonly TelegramConfirmationService $confirmation,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $expected = (string) config('services.telegram.webhook_secret');
        if ($expected === '') {
            return response()->json(['error' => 'Telegram webhook secret is not configured'], 503);
        }

        if (! hash_equals($expected, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))) {
            return response()->json(['error' => 'Invalid webhook secret'], 403);
        }

        $message = $request->input('message') ?: $request->input('edited_message');
        $chatId = data_get($message, 'chat.id');
        $telegramUserId = data_get($message, 'from.id');
        $text = trim((string) data_get($message, 'text', ''));

        if (! $chatId || ! $this->confirmation->isAuthorized((string) $chatId)) {
            return response()->json(['error' => 'Unauthorized Telegram chat'], 403);
        }

        $userId = $this->confirmation->userIdForChat((string) $chatId);
        $user = $userId ? User::find($userId) : null;
        if (! $user || ! $user->company_id) {
            return response()->json(['error' => 'Telegram chat is not mapped to a company user'], 403);
        }

        if ($text === '') {
            return response()->json(['accepted' => true, 'ignored' => true, 'reason' => 'empty_message']);
        }

        if (preg_match('/^\/?confirm\s+([A-Za-z0-9]+)$/', $text, $matches)) {
            return $this->confirmIssue($matches[1], (string) $chatId, $telegramUserId ? (string) $telegramUserId : null);
        }

        $draftPayload = $this->parser->parseIntent($text, $user->company_id);
        if (! empty($draftPayload['error'])) {
            return response()->json([
                'accepted' => true,
                'mode' => 'manual_fallback',
                'error' => $draftPayload['error'],
            ]);
        }

        $document = $this->workflow->createDraft([
            'company_id' => $user->company_id,
            'document_type' => $draftPayload['document_type'],
            'notes' => $draftPayload['notes'] ?? $text,
            'items' => $draftPayload['items'] ?? [],
        ]);

        $token = $this->confirmation->generateToken(
            $document,
            $user,
            (string) $chatId,
            $telegramUserId ? (string) $telegramUserId : null
        );

        return response()->json([
            'accepted' => true,
            'mode' => 'draft_created_confirmation_required',
            'document_id' => $document->id,
            'document_type' => $document->document_type,
            'draft_hash' => $document->draft_hash,
            'grand_total' => (float) $document->grand_total,
            'confirmation_token' => $token['token'],
            'expires_at' => $token['expires_at'],
        ], 201);
    }

    private function confirmIssue(string $plainToken, string $chatId, ?string $telegramUserId): JsonResponse
    {
        $token = $this->confirmation->consumeForIssue($plainToken, $chatId, $telegramUserId);
        if (! $token) {
            return response()->json(['error' => 'Invalid, expired, replayed, or stale confirmation token'], 409);
        }

        $document = $token->document;
        $issued = $this->workflow->issue(
            $document->id,
            $token->user_id,
            $token->draft_hash,
            (float) $document->grand_total
        );

        return response()->json([
            'accepted' => true,
            'mode' => 'issued_after_confirmation',
            'document_id' => $issued->id,
            'official_number' => $issued->official_number,
            'status' => $issued->status,
        ]);
    }
}
