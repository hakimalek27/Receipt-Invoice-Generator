<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $expected = config('services.telegram.webhook_secret');
        if ($expected && ! hash_equals($expected, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))) {
            return response()->json(['error' => 'Invalid webhook secret'], 403);
        }

        return response()->json([
            'accepted' => true,
            'mode' => 'draft_only_stub_until_r5',
        ]);
    }
}
