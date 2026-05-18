<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\NumberingPolicy;
use App\Models\User;
use App\Services\DocumentWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiSurfaceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private DocumentWorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['code' => 'WS']);
        $this->user = User::factory()->create([
            'role' => 'admin',
            'company_id' => $this->company->id,
        ]);
        $this->workflow = app(DocumentWorkflowService::class);

        foreach (['invoice' => 'INV', 'quotation' => 'Q', 'official_receipt' => 'REC'] as $type => $code) {
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

    public function test_required_api_route_surface_exists(): void
    {
        $routes = collect(Route::getRoutes())->map(fn ($route) => [
            'methods' => $route->methods(),
            'uri' => $route->uri(),
        ]);

        foreach ([
            ['PATCH', 'api/documents/{id}'],
            ['POST', 'api/documents/{id}/void'],
            ['POST', 'api/documents/{id}/convert'],
            ['GET', 'api/documents/{id}/pdf'],
            ['POST', 'api/payments'],
            ['POST', 'api/documents/{document}/attachments'],
            ['GET', 'api/customers'],
            ['POST', 'api/products'],
            ['GET', 'api/templates'],
            ['PATCH', 'api/templates/{template}'],
            ['GET', 'api/numbering-policies'],
            ['PATCH', 'api/numbering-policies/{policy}'],
            ['POST', 'api/ai/deepseek/parse-draft'],
            ['POST', 'api/telegram/webhook'],
        ] as [$method, $uri]) {
            $this->assertTrue(
                $routes->contains(fn ($route) => $route['uri'] === $uri && in_array($method, $route['methods'], true)),
                "{$method} {$uri} route missing"
            );
        }
    }

    public function test_document_update_void_convert_and_pdf_download_surface(): void
    {
        Sanctum::actingAs($this->user);

        $quotation = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'quotation',
            'items' => [['description' => 'Banner', 'quantity' => 1, 'unit_price' => 100]],
        ]);

        $this->patchJson("/api/documents/{$quotation->id}", ['terms' => 'Valid 14 days'])
            ->assertOk()
            ->assertJsonPath('terms', 'Valid 14 days');

        $this->postJson("/api/documents/{$quotation->id}/convert", ['target_type' => 'invoice'])
            ->assertCreated()
            ->assertJsonPath('document_type', 'invoice');

        $invoice = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'items' => [['description' => 'Receipt PDF', 'quantity' => 1, 'unit_price' => 50]],
        ]);
        $issued = $this->workflow->issue($invoice->id);

        $this->get("/api/documents/{$issued->id}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->postJson("/api/documents/{$issued->id}/void", ['reason' => 'Test cancellation'])
            ->assertOk()
            ->assertJsonPath('status', Document::STATUS_VOID);
    }

    public function test_pdf_download_is_company_scoped(): void
    {
        $otherCompany = Company::factory()->create(['code' => 'PGG']);
        $otherUser = User::factory()->create(['role' => 'admin', 'company_id' => $otherCompany->id]);
        $invoice = $this->workflow->issue($this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'items' => [['description' => 'X', 'quantity' => 1, 'unit_price' => 100]],
        ])->id);

        Sanctum::actingAs($otherUser);

        $this->get("/api/documents/{$invoice->id}/pdf")->assertForbidden();
    }

    public function test_payment_endpoint_can_create_official_receipt(): void
    {
        Sanctum::actingAs($this->user);
        $invoice = $this->workflow->issue($this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'items' => [['description' => 'X', 'quantity' => 1, 'unit_price' => 100]],
        ])->id);

        $this->postJson('/api/payments', [
            'amount' => 100,
            'create_official_receipt' => true,
            'allocations' => [
                ['document_id' => $invoice->id, 'amount' => 100],
            ],
        ])->assertCreated()
            ->assertJsonPath('receipt_document.document_type', 'official_receipt');
    }

    public function test_master_data_and_attachment_surfaces(): void
    {
        Sanctum::actingAs($this->user);
        Storage::fake('local');

        $this->postJson('/api/customers', [
            'name' => 'Customer A',
            'email' => 'customer@example.test',
        ])->assertCreated();

        $this->postJson('/api/products', [
            'name' => 'Banner',
            'default_price' => 120,
        ])->assertCreated();

        $this->get('/api/customers')->assertOk();
        $this->get('/api/products')->assertOk();
        $this->get('/api/templates')->assertOk();
        $this->get('/api/numbering-policies')->assertOk();

        $template = DocumentTemplate::create([
            'company_id' => $this->company->id,
            'name' => 'Default Invoice',
            'document_type' => 'invoice',
            'paper_size' => 'A4',
            'is_active' => true,
        ]);
        $this->patchJson("/api/templates/{$template->id}", [
            'show_amount_in_words' => true,
            'amount_in_words_label' => 'Jumlah dalam perkataan',
        ])->assertOk()
            ->assertJsonPath('show_amount_in_words', true);

        $policy = NumberingPolicy::forCompany($this->company->id)->forType('invoice')->firstOrFail();
        $this->patchJson("/api/numbering-policies/{$policy->id}", [
            'prefix' => 'WS-INV',
            'sequence_padding' => 6,
        ])->assertOk()
            ->assertJsonPath('prefix', 'WS-INV');

        $document = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'customer_id' => Customer::forCompany($this->company->id)->first()->id,
            'items' => [['description' => 'X', 'quantity' => 1, 'unit_price' => 100]],
        ]);

        $this->postJson("/api/documents/{$document->id}/attachments", [
            'file' => UploadedFile::fake()->create('artwork.pdf', 20, 'application/pdf'),
            'caption' => 'Artwork 1',
        ])->assertCreated()
            ->assertJsonPath('caption', 'Artwork 1');

        $this->get("/api/documents/{$document->id}/attachments")->assertOk();
    }

    public function test_ai_parse_and_telegram_webhook_routes_exist_and_respond(): void
    {
        Sanctum::actingAs($this->user);
        config([
            'services.telegram.webhook_secret' => 'test-secret',
            'services.telegram.allowed_chat_ids' => '11111',
            'services.telegram.chat_user_map' => '11111:'.$this->user->id,
        ]);

        $this->postJson('/api/ai/deepseek/parse-draft', [
            'message' => 'Invoice 2x Banner RM100',
        ])->assertOk()
            ->assertJsonPath('company_id', $this->company->id);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-secret')
            ->postJson('/api/telegram/webhook', [
                'message' => [
                    'chat' => ['id' => 11111],
                    'from' => ['id' => 22222],
                    'text' => 'draft invoice 1x Banner RM100',
                ],
            ])->assertCreated()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('mode', 'draft_created_confirmation_required');
    }
}
