<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyBankAccount;
use App\Models\Customer;
use App\Models\NumberingPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * End-to-end smoke pass exercising every Master Data feature the user
 * expected to see in the production dashboard but didn't (because the
 * branch hasn't been deployed yet). Each test hits the real API/Inertia
 * route — passes here = will work in production after deploy.
 */
class FullStackSmokeTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->company = Company::factory()->wehdah()->create();
        $this->user = User::factory()->create([
            'role' => 'admin',
            'company_id' => $this->company->id,
        ]);
        Sanctum::actingAs($this->user);
    }

    public function test_master_data_page_renders_with_all_tabs(): void
    {
        $this->actingAs($this->user)->get('/master-data')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('MasterData/Index')
                ->has('company')
                ->has('bankAccounts')
                ->has('customers')
                ->has('products')
                ->has('templates')
                ->has('numberingPolicies')
                ->has('documentTypes')
            );
    }

    public function test_company_name_address_phone_email_can_be_updated(): void
    {
        $payload = [
            'name' => 'Wehdah Solution Updated',
            'address' => 'New Address Line 1',
            'phone' => '+603-12345678',
            'email' => 'newmail@wehdah.test',
            'registration_number' => 'PG0514579-H',
        ];
        $this->patchJson("/api/companies/{$this->company->id}", $payload)
            ->assertOk()
            ->assertJsonPath('name', 'Wehdah Solution Updated')
            ->assertJsonPath('phone', '+603-12345678')
            ->assertJsonPath('email', 'newmail@wehdah.test')
            ->assertJsonPath('registration_number', 'PG0514579-H');
    }

    public function test_brand_colors_can_be_updated_via_api(): void
    {
        $this->patchJson("/api/companies/{$this->company->id}", [
            'brand_primary' => '#002060',
            'brand_secondary' => '#f4f7fa',
            'brand_accent' => '#1F3A5F',
        ])->assertOk()
            ->assertJsonPath('brand_primary', '#002060')
            ->assertJsonPath('brand_accent', '#1F3A5F');
    }

    public function test_logo_upload_persists_path_and_renders_url(): void
    {
        $response = $this->postJson("/api/companies/{$this->company->id}/branding/logo", [
            'file' => UploadedFile::fake()->image('logo.png', 200, 200),
        ])->assertOk()
            ->assertJsonStructure(['kind', 'path', 'url']);

        $this->assertSame('logo', $response->json('kind'));
        $this->company->refresh();
        $this->assertNotNull($this->company->logo_path);
        $this->assertNotNull($this->company->logo_url);
    }

    public function test_stamp_upload_persists(): void
    {
        $this->postJson("/api/companies/{$this->company->id}/branding/stamp", [
            'file' => UploadedFile::fake()->image('stamp.png', 200, 200),
        ])->assertOk()
            ->assertJsonPath('kind', 'stamp');

        $this->assertNotNull($this->company->fresh()->stamp_path);
    }

    public function test_signature_upload_persists(): void
    {
        $this->postJson("/api/companies/{$this->company->id}/branding/signature", [
            'file' => UploadedFile::fake()->image('signature.png', 300, 100),
        ])->assertOk()
            ->assertJsonPath('kind', 'signature');

        $this->assertNotNull($this->company->fresh()->signature_path);
    }

    public function test_branding_can_be_removed(): void
    {
        $this->postJson("/api/companies/{$this->company->id}/branding/logo", [
            'file' => UploadedFile::fake()->image('logo.png', 200, 200),
        ])->assertOk();

        $this->deleteJson("/api/companies/{$this->company->id}/branding/logo")
            ->assertOk();

        $this->company->refresh();
        $this->assertNull($this->company->logo_path);
    }

    public function test_bank_account_crud_full_cycle(): void
    {
        $created = $this->postJson("/api/companies/{$this->company->id}/bank-accounts", [
            'bank_name' => 'Hong Leong Islamic',
            'account_number' => '18701038380',
            'is_primary' => true,
            'sort_order' => 1,
        ])->assertCreated()->json();

        $this->patchJson("/api/companies/{$this->company->id}/bank-accounts/{$created['id']}", [
            'account_number' => '99988877766',
        ])->assertOk()->assertJsonPath('account_number', '99988877766');

        $this->getJson("/api/companies/{$this->company->id}/bank-accounts")
            ->assertOk()
            ->assertJsonCount(1);

        $this->deleteJson("/api/companies/{$this->company->id}/bank-accounts/{$created['id']}")
            ->assertOk();

        $this->getJson("/api/companies/{$this->company->id}/bank-accounts")
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_pdf_boilerplate_per_doc_type_persists_and_returns(): void
    {
        $this->patchJson("/api/companies/{$this->company->id}", [
            'pdf_boilerplate' => [
                'invoice' => [
                    'footer_terms' => 'Custom invoice footer.',
                    'signature_left_intro' => 'Yang ikhlas,',
                ],
                'official_receipt' => [
                    'intro' => 'Diterima dengan rasa terima kasih:',
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('pdf_boilerplate.invoice.footer_terms', 'Custom invoice footer.')
            ->assertJsonPath('pdf_boilerplate.invoice.signature_left_intro', 'Yang ikhlas,')
            ->assertJsonPath('pdf_boilerplate.official_receipt.intro', 'Diterima dengan rasa terima kasih:');
    }

    public function test_customer_create_update_delete_via_api(): void
    {
        $customer = $this->postJson('/api/customers', [
            'name' => 'Test Customer',
            'email' => 'test@example.com',
            'phone' => '012-3456789',
        ])->assertCreated()->json();

        $this->patchJson("/api/customers/{$customer['id']}", [
            'phone' => '019-9999999',
        ])->assertOk()->assertJsonPath('phone', '019-9999999');

        $this->deleteJson("/api/customers/{$customer['id']}")
            ->assertOk()
            ->assertJsonPath('deleted', true);
    }

    public function test_product_create_update_delete_via_api(): void
    {
        $product = $this->postJson('/api/products', [
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'default_price' => 99.99,
            'uom' => 'pcs',
        ])->assertCreated()->json();

        $this->patchJson("/api/products/{$product['id']}", [
            'default_price' => 79.99,
        ])->assertOk()->assertJsonPath('default_price', '79.99');

        $this->deleteJson("/api/products/{$product['id']}")
            ->assertOk()
            ->assertJsonPath('deleted', true);
    }

    public function test_template_create_then_delete_via_api(): void
    {
        $tpl = $this->postJson('/api/templates', [
            'name' => 'Default Invoice',
            'document_type' => 'invoice',
            'paper_size' => 'A4',
            'is_active' => true,
        ])->assertCreated()->json();

        $this->deleteJson("/api/templates/{$tpl['id']}")->assertOk();
    }

    public function test_numbering_policy_create_then_delete_via_api(): void
    {
        $policy = $this->postJson('/api/numbering-policies', [
            'document_type' => 'cash_bill',
            'prefix' => 'WS-CB',
            'separator' => '-',
            'year_token' => '{YYYY}',
            'sequence_padding' => 5,
            'reset_policy' => 'yearly',
            'is_active' => true,
        ])->assertCreated()->json();

        $this->deleteJson("/api/numbering-policies/{$policy['id']}")->assertOk();
    }

    public function test_inertia_share_includes_active_company_and_available_companies(): void
    {
        $this->actingAs($this->user)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->has('auth.user')
                ->has('auth.active_company')
                ->has('auth.available_companies')
                ->where('auth.active_company.code', 'WS')
            );
    }

    public function test_documents_index_serves_all_filters_and_chain_relations(): void
    {
        NumberingPolicy::create([
            'company_id' => $this->company->id, 'document_type' => 'invoice',
            'prefix' => 'WS-INV', 'separator' => '-', 'year_token' => '{YYYY}',
            'sequence_padding' => 5, 'reset_policy' => 'yearly', 'is_active' => true,
        ]);

        $this->actingAs($this->user)->get('/documents?date_from=2025-01-01&date_to=2025-12-31&status=draft&search=test')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Documents/Index')
                ->where('filters.date_from', '2025-01-01')
                ->where('filters.date_to', '2025-12-31')
                ->where('filters.status', 'draft')
                ->where('filters.search', 'test')
                ->has('documentTypes')
            );
    }

    public function test_pdf_uses_redesigned_wehdah_template_not_generic(): void
    {
        $workflow = app(\App\Services\DocumentWorkflowService::class);
        $service = app(\App\Services\PdfRenderService::class);
        NumberingPolicy::create([
            'company_id' => $this->company->id, 'document_type' => 'invoice',
            'prefix' => 'WS-INV', 'separator' => '-', 'year_token' => '{YYYY}',
            'sequence_padding' => 5, 'reset_policy' => 'yearly', 'is_active' => true,
        ]);

        $doc = $workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'items' => [['description' => 'Test', 'quantity' => 1, 'unit_price' => 100]],
        ]);

        $reflection = new \ReflectionMethod($service, 'resolveTemplate');
        $reflection->setAccessible(true);
        $template = $reflection->invoke($service, 'invoice', 'A4', $this->company->id);

        // Critical: production must resolve to the WS-specific template,
        // not the generic Malay-style fallback.
        $this->assertSame('pdf.wehdah.invoice', $template);

        $html = view($template, $service->renderData($doc))->render();
        $this->assertStringContainsString('ws-title-strip', $html, 'WS template marker missing');
        $this->assertStringContainsString('INVOICE', $html);
        $this->assertStringNotContainsString('JUMLAH BESAR', $html, 'Old generic Malay template is being used');
        $this->assertStringNotContainsString('Kepada / Bill To', $html, 'Old generic Malay template is being used');
    }
}
