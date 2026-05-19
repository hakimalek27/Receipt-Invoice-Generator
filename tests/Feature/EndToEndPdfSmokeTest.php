<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyBankAccount;
use App\Models\Customer;
use App\Models\Document;
use App\Models\NumberingPolicy;
use App\Models\User;
use App\Services\DocumentWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EndToEndPdfSmokeTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private Customer $customer;

    private DocumentWorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->company = Company::factory()->wehdah()->create([
            'brand_primary' => '#002060',
            'brand_secondary' => '#f4f7fa',
            'brand_accent' => '#1F3A5F',
        ]);
        CompanyBankAccount::create([
            'company_id' => $this->company->id,
            'bank_name' => 'Hong Leong Islamic',
            'account_number' => '18701038380',
            'is_primary' => true,
            'sort_order' => 1,
        ]);

        foreach (['invoice' => 'INV', 'quotation' => 'QUO', 'delivery_order' => 'DO', 'official_receipt' => 'OR'] as $type => $code) {
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

        $this->user = User::factory()->create([
            'role' => 'admin',
            'company_id' => $this->company->id,
        ]);

        $this->customer = Customer::create([
            'company_id' => $this->company->id,
            'name' => 'E2E Test Customer',
            'address' => "Test Address Line 1\nTest Address Line 2\n50450 KL",
            'phone' => '019-1234567',
            'email' => 'e2e@test.local',
            'is_active' => true,
        ]);

        $this->workflow = app(DocumentWorkflowService::class);

        Sanctum::actingAs($this->user);
    }

    public function test_simple_invoice_full_api_flow_produces_valid_pdf(): void
    {
        $draft = $this->postJson('/api/documents', [
            'document_type' => 'invoice',
            'customer_id' => $this->customer->id,
            'document_date' => '2025-06-10',
            'due_date' => '2025-06-10',
            'currency' => 'MYR',
            'show_amount_in_words' => true,
            'amount_in_words_locale' => 'en_WEHDAH',
            'amount_in_words_currency' => 'MYR',
            'items' => [
                ['description' => 'Bunting 440gsm 1200dpi 2ft x 4ft', 'quantity' => 6, 'uom' => 'pcs', 'unit_price' => 10.00, 'discount' => 0],
                ['description' => 'Tripod Stand', 'quantity' => 6, 'uom' => 'pcs', 'unit_price' => 15.00, 'discount' => 0],
                ['description' => 'Framed Poster', 'section_header' => 'Display Items', 'quantity' => 2, 'uom' => 'pcs', 'unit_price' => 75.00, 'discount' => 5.00],
            ],
        ])->assertCreated()->json();

        $issued = $this->issueDocument($draft['id'], $draft['draft_hash'], $draft['grand_total']);

        $this->assertSame('WS-INV-2025-00001', $issued['official_number']);
        $this->assertSame(Document::STATUS_ISSUED, $issued['status']);

        $response = $this->get("/api/documents/{$issued['id']}/pdf");
        $response->assertOk()->assertHeader('content-type', 'application/pdf');

        $pdfBytes = $response->streamedContent();
        $this->assertStringStartsWith('%PDF-', $pdfBytes);
        $this->assertGreaterThan(10000, strlen($pdfBytes));
    }

    public function test_large_22_item_invoice_renders_multipage_pdf(): void
    {
        $items = [];
        foreach (range(1, 22) as $i) {
            $items[] = [
                'description' => "Item line {$i} sample description",
                'quantity' => 1,
                'uom' => 'pcs',
                'unit_price' => 10.00 + $i,
                'discount' => 0,
            ];
        }

        $draft = $this->postJson('/api/documents', [
            'document_type' => 'invoice',
            'customer_id' => $this->customer->id,
            'currency' => 'MYR',
            'show_amount_in_words' => true,
            'amount_in_words_locale' => 'en_WEHDAH',
            'amount_in_words_currency' => 'MYR',
            'items' => $items,
        ])->assertCreated()->json();

        $issued = $this->issueDocument($draft['id'], $draft['draft_hash'], $draft['grand_total']);

        $this->get("/api/documents/{$issued['id']}/pdf")->assertOk();

        $document = Document::with('pdfRenders')->find($issued['id']);
        $currentRender = $document->pdfRenders->where('is_current', true)->first();
        $this->assertNotNull($currentRender);
        $this->assertGreaterThanOrEqual(2, $currentRender->page_count, '22-item invoice should span multiple pages');
    }

    public function test_quote_with_attachments_full_api_flow(): void
    {
        $draft = $this->postJson('/api/documents', [
            'document_type' => 'quotation',
            'customer_id' => $this->customer->id,
            'currency' => 'MYR',
            'items' => [
                ['description' => 'Logo Design', 'quantity' => 1, 'uom' => 'job', 'unit_price' => 850.00, 'discount' => 0],
                ['description' => 'Brand Guidelines', 'quantity' => 1, 'uom' => 'doc', 'unit_price' => 450.00, 'discount' => 0],
            ],
        ])->assertCreated()->json();

        $attachment1 = $this->postJson("/api/documents/{$draft['id']}/attachments", [
            'file' => UploadedFile::fake()->image('artwork-concept-a.png', 800, 600),
            'caption' => 'Logo Concept A',
        ])->assertCreated()->json();

        $attachment2 = $this->postJson("/api/documents/{$draft['id']}/attachments", [
            'file' => UploadedFile::fake()->image('artwork-concept-b.png', 800, 600),
            'caption' => 'Logo Concept B',
        ])->assertCreated()->json();

        $this->assertSame('Logo Concept A', $attachment1['caption']);
        $this->assertSame('Logo Concept B', $attachment2['caption']);

        $issued = $this->issueDocument($draft['id'], $draft['draft_hash'], $draft['grand_total']);

        $this->assertStringContainsString('WS-QUO', $issued['official_number']);

        $this->get("/api/documents/{$issued['id']}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $document = Document::with('attachments', 'pdfRenders')->find($issued['id']);
        $this->assertCount(2, $document->attachments);
    }

    public function test_official_receipt_via_payment_endpoint_produces_pdf(): void
    {
        $invoiceDraft = $this->postJson('/api/documents', [
            'document_type' => 'invoice',
            'customer_id' => $this->customer->id,
            'currency' => 'MYR',
            'items' => [['description' => 'Service', 'quantity' => 1, 'uom' => 'job', 'unit_price' => 295.00]],
        ])->assertCreated()->json();

        $invoice = $this->issueDocument($invoiceDraft['id'], $invoiceDraft['draft_hash'], $invoiceDraft['grand_total']);

        $payment = $this->postJson('/api/payments', [
            'amount' => 295.00,
            'reference_number' => 'BIMB-2025-06-12-001',
            'payment_date' => '2025-06-12',
            'method' => 'bank_transfer',
            'create_official_receipt' => true,
            'allocations' => [['document_id' => $invoice['id'], 'amount' => 295.00]],
        ])->assertCreated()->json();

        $this->assertNotNull($payment['receipt_document']['id']);

        $this->get("/api/documents/{$payment['receipt_document']['id']}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_documents_edit_inertia_page_loads(): void
    {
        $document = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'customer_id' => $this->customer->id,
            'items' => [['description' => 'Test', 'quantity' => 1, 'unit_price' => 100]],
        ]);

        $response = $this->actingAs($this->user)->get("/documents/{$document->id}");
        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Documents/Edit')
                ->has('document')
                ->has('customers')
                ->has('products')
                ->has('company')
            );
    }

    public function test_payments_index_page_loads(): void
    {
        $response = $this->actingAs($this->user)->get('/payments');
        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payments/Index')
                ->has('payments')
                ->has('receivableDocuments')
            );
    }

    public function test_pdf_download_company_scoping_blocks_cross_company_access(): void
    {
        $otherCompany = Company::factory()->create(['code' => 'OTHER']);
        $otherUser = User::factory()->create([
            'role' => 'admin',
            'company_id' => $otherCompany->id,
        ]);

        $draft = $this->postJson('/api/documents', [
            'document_type' => 'invoice',
            'currency' => 'MYR',
            'items' => [['description' => 'Sensitive', 'quantity' => 1, 'unit_price' => 100]],
        ])->assertCreated()->json();

        $issued = $this->issueDocument($draft['id'], $draft['draft_hash'], $draft['grand_total']);

        Sanctum::actingAs($otherUser);
        $this->get("/api/documents/{$issued['id']}/pdf")->assertForbidden();
    }

    private function issueDocument(int $documentId, string $draftHash, float $confirmedTotal): array
    {
        return $this->withHeader('Idempotency-Key', 'smoke-'.uniqid())
            ->postJson("/api/documents/{$documentId}/issue", [
                'draft_hash' => $draftHash,
                'confirmed_total' => $confirmedTotal,
            ])
            ->assertOk()
            ->json();
    }
}
