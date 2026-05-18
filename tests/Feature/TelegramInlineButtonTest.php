<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Document;
use App\Models\NumberingPolicy;
use App\Models\TelegramConfirmationToken;
use App\Models\User;
use App\Services\DeepSeekParserService;
use App\Services\DocumentWorkflowService;
use App\Services\TelegramConfirmationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramInlineButtonTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private TelegramConfirmationService $confirmation;

    private DocumentWorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['code' => 'WS']);
        $this->user = User::factory()->create([
            'role' => 'admin',
            'company_id' => $this->company->id,
        ]);

        config([
            'services.telegram.bot_token' => 'test-bot-token',
            'services.telegram.webhook_secret' => 'test-secret',
            'services.telegram.allowed_chat_ids' => '11111',
            'services.telegram.chat_user_map' => '11111:'.$this->user->id,
            'services.deepseek.api_key' => null,
        ]);

        $this->confirmation = app(TelegramConfirmationService::class);
        $this->workflow = app(DocumentWorkflowService::class);

        foreach (['invoice' => 'INV', 'official_receipt' => 'REC'] as $type => $code) {
            NumberingPolicy::create([
                'company_id' => $this->company->id,
                'document_type' => $type,
                'prefix' => 'WS-'.$code,
                'separator' => '-',
                'year_token' => '{YYYY}',
                'sequence_padding' => 5,
                'reset_policy' => 'yearly',
                'is_active' => true,
            ]);
        }

        Http::fake([
            'https://api.telegram.org/bottest-bot-token/sendMessage' => Http::response(['ok' => true], 200),
            'https://api.telegram.org/bottest-bot-token/sendDocument' => Http::response(['ok' => true], 200),
            'https://api.telegram.org/bottest-bot-token/answerCallbackQuery' => Http::response(['ok' => true], 200),
        ]);
    }

    public function test_approve_callback_query_issues_document(): void
    {
        [$document, $plainToken] = $this->seedDraftWithToken();

        $this->postCallback('approve:'.$plainToken)->assertOk()
            ->assertJsonPath('mode', 'issued_after_callback')
            ->assertJsonPath('status', Document::STATUS_ISSUED);

        $document->refresh();
        $this->assertSame(Document::STATUS_ISSUED, $document->status);
        $this->assertNotNull($document->official_number);

        Http::assertSent(fn ($req) => str_contains($req->url(), '/answerCallbackQuery'));
    }

    public function test_reject_callback_query_soft_deletes_draft(): void
    {
        [$document, $plainToken] = $this->seedDraftWithToken();

        $this->postCallback('reject:'.$plainToken)->assertOk()
            ->assertJsonPath('mode', 'rejected_after_callback');

        $this->assertNull(Document::find($document->id));
        $this->assertNotNull(Document::withTrashed()->find($document->id)->deleted_at);

        $token = TelegramConfirmationToken::first();
        $this->assertNotNull($token->used_at);
    }

    public function test_callback_query_from_unauthorized_chat_returns_403(): void
    {
        [, $plainToken] = $this->seedDraftWithToken();

        $this->postCallbackFromChat('approve:'.$plainToken, '99999')
            ->assertForbidden()
            ->assertJsonPath('error', 'Unauthorized Telegram chat');
    }

    public function test_callback_query_with_expired_token_returns_409(): void
    {
        [, $plainToken] = $this->seedDraftWithToken();
        TelegramConfirmationToken::query()->update(['expires_at' => now()->subMinute()]);

        $this->postCallback('approve:'.$plainToken)
            ->assertStatus(409)
            ->assertJsonPath('error', 'Invalid, expired, replayed, or stale confirmation token');
    }

    public function test_callback_query_replay_returns_409(): void
    {
        [, $plainToken] = $this->seedDraftWithToken();

        $this->postCallback('approve:'.$plainToken)->assertOk();
        $this->postCallback('approve:'.$plainToken)->assertStatus(409);
    }

    private function seedDraftWithToken(): array
    {
        $document = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'items' => [['description' => 'Test item', 'quantity' => 1, 'unit_price' => 100]],
        ]);

        $token = $this->confirmation->generateToken($document, $this->user, '11111', '22222');

        return [$document, $token['token']];
    }

    private function postCallback(string $data, string $callbackId = 'cb-123')
    {
        return $this->postCallbackFromChat($data, '11111', $callbackId);
    }

    private function postCallbackFromChat(string $data, string $chatId, string $callbackId = 'cb-123')
    {
        return $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-secret')
            ->postJson('/api/telegram/webhook', [
                'callback_query' => [
                    'id' => $callbackId,
                    'from' => ['id' => 22222],
                    'message' => ['chat' => ['id' => (int) $chatId]],
                    'data' => $data,
                ],
            ]);
    }
}
