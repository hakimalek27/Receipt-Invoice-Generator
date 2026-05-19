<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\NumberingPolicy;
use App\Models\TelegramMessage;
use App\Models\User;
use App\Services\DeepSeekParserService;
use App\Services\DocumentWorkflowService;
use App\Services\TelegramConfirmationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TelegramPdfAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private TelegramConfirmationService $confirmation;

    private DocumentWorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

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
    }

    public function test_draft_event_listener_attaches_pdf_with_inline_keyboard(): void
    {
        Http::fake([
            'https://api.telegram.org/bottest-bot-token/sendMessage' => Http::response(['ok' => true], 200),
            'https://api.telegram.org/bottest-bot-token/sendDocument' => Http::response(['ok' => true], 200),
        ]);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-secret')
            ->postJson('/api/telegram/webhook', [
                'message' => [
                    'chat' => ['id' => 11111],
                    'from' => ['id' => 22222],
                    'text' => 'invoice 1x Banner RM100',
                ],
            ])->assertCreated();

        Http::assertSent(function ($req) {
            if (! str_contains($req->url(), '/sendDocument')) {
                return false;
            }
            $body = (string) $req->body();
            // multipart body should reference the document field and a reply_markup with inline_keyboard
            return str_contains($body, 'document') && str_contains($body, 'reply_markup') && str_contains($body, 'inline_keyboard');
        });

        $sendDocumentMessage = TelegramMessage::where('direction', 'outbound')
            ->whereJsonContains('payload_redacted->method', 'sendDocument')
            ->firstOrFail();
        $this->assertSame('sent', $sendDocumentMessage->status);
        $this->assertTrue($sendDocumentMessage->payload_redacted['has_reply_markup']);
        $this->assertStringEndsWith('.pdf', $sendDocumentMessage->payload_redacted['document_filename']);
    }

    public function test_issued_event_listener_attaches_issued_pdf(): void
    {
        Http::fake([
            'https://api.telegram.org/bottest-bot-token/sendMessage' => Http::response(['ok' => true], 200),
            'https://api.telegram.org/bottest-bot-token/sendDocument' => Http::response(['ok' => true], 200),
            'https://api.telegram.org/bottest-bot-token/answerCallbackQuery' => Http::response(['ok' => true], 200),
        ]);

        $document = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'items' => [['description' => 'Test', 'quantity' => 1, 'unit_price' => 100]],
        ]);
        $token = $this->confirmation->generateToken($document, $this->user, '11111', '22222');

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-secret')
            ->postJson('/api/telegram/webhook', [
                'callback_query' => [
                    'id' => 'cb-1',
                    'from' => ['id' => 22222],
                    'message' => ['chat' => ['id' => 11111]],
                    'data' => 'approve:'.$token['token'],
                ],
            ])->assertOk();

        $issuedSummary = TelegramMessage::where('direction', 'outbound')
            ->whereJsonContains('payload_redacted->method', 'sendDocument')
            ->where('document_id', $document->id)
            ->firstOrFail();

        $this->assertSame('sent', $issuedSummary->status);
        $this->assertFalse($issuedSummary->payload_redacted['has_reply_markup']);
        $this->assertStringContainsString($document->fresh()->official_number, $issuedSummary->payload_redacted['caption'] ?? '');
    }

    public function test_send_document_failure_recorded_as_failed_status(): void
    {
        Http::fake([
            'https://api.telegram.org/bottest-bot-token/sendMessage' => Http::response(['ok' => true], 200),
            'https://api.telegram.org/bottest-bot-token/sendDocument' => Http::response(['ok' => false, 'description' => 'PDF too large'], 500),
        ]);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-secret')
            ->postJson('/api/telegram/webhook', [
                'message' => [
                    'chat' => ['id' => 11111],
                    'from' => ['id' => 22222],
                    'text' => 'invoice 1x Banner RM100',
                ],
            ])->assertCreated();

        $sendDocumentMessage = TelegramMessage::where('direction', 'outbound')
            ->whereJsonContains('payload_redacted->method', 'sendDocument')
            ->firstOrFail();
        $this->assertSame('failed', $sendDocumentMessage->status);
        $this->assertNotNull($sendDocumentMessage->error);
    }
}
