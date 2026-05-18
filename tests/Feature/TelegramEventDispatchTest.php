<?php

namespace Tests\Feature;

use App\Events\DocumentDrafted;
use App\Events\DocumentIssued;
use App\Events\DocumentRejected;
use App\Models\Company;
use App\Models\NumberingPolicy;
use App\Models\User;
use App\Services\DocumentWorkflowService;
use App\Services\TelegramConfirmationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramEventDispatchTest extends TestCase
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

        Http::fake();
    }

    public function test_document_drafted_event_dispatched_on_text_webhook(): void
    {
        Event::fake([DocumentDrafted::class]);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-secret')
            ->postJson('/api/telegram/webhook', [
                'message' => [
                    'chat' => ['id' => 11111],
                    'from' => ['id' => 22222],
                    'text' => 'invoice 1x Banner RM100',
                ],
            ])->assertCreated();

        Event::assertDispatched(DocumentDrafted::class, function (DocumentDrafted $event) {
            return $event->chatId === '11111'
                && $event->userId === $this->user->id
                && $event->documentId > 0
                && $event->tokenId > 0
                && strlen($event->tokenPlain) >= 32;
        });
    }

    public function test_document_rejected_event_dispatched_on_callback_query_reject(): void
    {
        $document = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'items' => [['description' => 'Test', 'quantity' => 1, 'unit_price' => 100]],
        ]);
        $token = $this->confirmation->generateToken($document, $this->user, '11111', '22222');

        Event::fake([DocumentRejected::class, DocumentIssued::class]);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-secret')
            ->postJson('/api/telegram/webhook', [
                'callback_query' => [
                    'id' => 'cb-1',
                    'from' => ['id' => 22222],
                    'message' => ['chat' => ['id' => 11111]],
                    'data' => 'reject:'.$token['token'],
                ],
            ])->assertOk();

        Event::assertDispatched(DocumentRejected::class, function (DocumentRejected $event) use ($document) {
            return $event->documentId === $document->id
                && $event->chatId === '11111';
        });
        Event::assertNotDispatched(DocumentIssued::class);
    }
}
