<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\NumberingPolicy;
use App\Services\DocumentWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected DocumentWorkflowService $workflow;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workflow = app(DocumentWorkflowService::class);
        $this->company = Company::factory()->create(['code' => 'WS']);

        NumberingPolicy::create([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'prefix' => 'WS',
            'separator' => '-',
            'year_token' => '{YYYY}',
            'sequence_padding' => 5,
            'reset_policy' => 'yearly',
            'is_active' => true,
        ]);
        NumberingPolicy::create([
            'company_id' => $this->company->id,
            'document_type' => 'quotation',
            'prefix' => 'WS',
            'separator' => '-',
            'year_token' => '{YYYY}',
            'sequence_padding' => 5,
            'reset_policy' => 'yearly',
            'is_active' => true,
        ]);
        NumberingPolicy::create([
            'company_id' => $this->company->id,
            'document_type' => 'official_receipt',
            'prefix' => 'WS',
            'separator' => '-',
            'year_token' => '{YYYY}',
            'sequence_padding' => 5,
            'reset_policy' => 'yearly',
            'is_active' => true,
        ]);
    }

    private function createDraftItems(): array
    {
        return [
            ['description' => 'Item A', 'quantity' => 2, 'unit_price' => 100, 'sort_order' => 0],
            ['description' => 'Item B', 'quantity' => 1, 'unit_price' => 250, 'sort_order' => 1],
        ];
    }

    public function test_draft_to_issue(): void
    {
        $draft = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'items' => $this->createDraftItems(),
        ]);

        $this->assertEquals(Document::STATUS_DRAFT, $draft->status);
        $this->assertNull($draft->official_number);
        $this->assertEquals(450, (float) $draft->grand_total);

        // Issue
        $issued = $this->workflow->issue($draft->id);
        $this->assertEquals(Document::STATUS_ISSUED, $issued->status);
        $this->assertEquals('WS-2026-00001', $issued->official_number);
        $this->assertNotNull($issued->issued_at);
        $this->assertNotNull($issued->issuer_snapshot_json);
    }

    public function test_only_draft_can_be_issued(): void
    {
        $draft = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'items' => $this->createDraftItems(),
        ]);

        $this->workflow->issue($draft->id);

        $this->expectException(\RuntimeException::class);
        $this->workflow->issue($draft->id);
    }

    public function test_quotation_to_invoice_conversion(): void
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);

        $quotation = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'quotation',
            'customer_id' => $customer->id,
            'items' => $this->createDraftItems(),
        ]);

        $this->assertEquals('quotation', $quotation->document_type);

        // Convert to invoice
        $invoice = $this->workflow->convert($quotation->id, 'invoice');
        $this->assertEquals('invoice', $invoice->document_type);
        $this->assertEquals(Document::STATUS_DRAFT, $invoice->status);
        $this->assertEquals($quotation->id, $invoice->converted_from_id);
        $this->assertCount(2, $invoice->items);

        // Issue the invoice
        $issued = $this->workflow->issue($invoice->id);
        $this->assertEquals('WS-2026-00001', $issued->official_number);
    }

    public function test_partial_payment_and_allocation(): void
    {
        $draft = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'items' => $this->createDraftItems(),
        ]);
        $invoice = $this->workflow->issue($draft->id);
        $this->assertEquals(450, (float) $invoice->grand_total);

        // Record partial payment
        $payment = $this->workflow->recordPayment([
            'company_id' => $this->company->id,
            'amount' => 200,
            'method' => 'bank_transfer',
            'allocations' => [
                ['document_id' => $invoice->id, 'amount' => 200],
            ],
        ]);

        $this->assertEquals(200, (float) $payment->amount);
        $this->assertEquals(0, (float) $payment->unallocated_amount);
        $this->assertCount(1, $payment->allocations);
    }

    public function test_multi_invoice_payment(): void
    {
        // Create two invoices
        $inv1 = $this->workflow->issue(
            $this->workflow->createDraft([
                'company_id' => $this->company->id,
                'document_type' => 'invoice',
                'items' => [['description' => 'X', 'quantity' => 1, 'unit_price' => 300]],
            ])->id
        );

        $inv2 = $this->workflow->issue(
            $this->workflow->createDraft([
                'company_id' => $this->company->id,
                'document_type' => 'invoice',
                'items' => [['description' => 'Y', 'quantity' => 1, 'unit_price' => 200]],
            ])->id
        );

        // Single payment covering both
        $payment = $this->workflow->recordPayment([
            'company_id' => $this->company->id,
            'amount' => 500,
            'allocations' => [
                ['document_id' => $inv1->id, 'amount' => 300],
                ['document_id' => $inv2->id, 'amount' => 200],
            ],
        ]);

        $this->assertEquals(0, (float) $payment->fresh()->unallocated_amount);
        $this->assertCount(2, $payment->allocations);
    }

    public function test_void_with_reason(): void
    {
        $draft = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'items' => $this->createDraftItems(),
        ]);
        $issued = $this->workflow->issue($draft->id);

        $voided = $this->workflow->void($issued->id, 'Customer cancelled order');
        $this->assertEquals(Document::STATUS_VOID, $voided->status);
        $this->assertEquals('Customer cancelled order', $voided->void_reason);
        $this->assertNotNull($voided->voided_at);

        // Status history recorded
        $this->assertCount(3, $voided->statusHistory);
    }

    public function test_cannot_void_already_voided(): void
    {
        $draft = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'items' => $this->createDraftItems(),
        ]);
        $issued = $this->workflow->issue($draft->id);
        $this->workflow->void($issued->id, 'Reason A');

        $this->expectException(\RuntimeException::class);
        $this->workflow->void($issued->id, 'Reason B');
    }

    public function test_issued_snapshots_unchanged_after_profile_edit(): void
    {
        $draft = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'items' => $this->createDraftItems(),
        ]);
        $issued = $this->workflow->issue($draft->id);

        $snapshot = $issued->issuer_snapshot_json;

        // Edit company profile
        $this->company->update(['name' => 'Changed Name', 'phone' => '555-0000']);
        $this->assertEquals('Changed Name', $this->company->fresh()->name);

        // Snapshot must be unchanged
        $this->assertEquals($snapshot, $issued->fresh()->issuer_snapshot_json);
        $this->assertNotEquals('Changed Name', $issued->fresh()->issuer_snapshot_json['name']);
    }

    public function test_document_recomputes_totals_correctly(): void
    {
        $draft = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'items' => [
                ['description' => 'X', 'quantity' => 2, 'unit_price' => 100, 'sort_order' => 0],
                ['description' => 'Y', 'quantity' => 1, 'unit_price' => 50, 'discount' => 10, 'sort_order' => 1],
            ],
        ]);

        // X: 2*100 = 200, Y: 1*50 - 10 = 40
        $this->assertEquals(240, (float) $draft->subtotal);
        $this->assertEquals(10, (float) $draft->discount_total);
        $this->assertEquals(230, (float) $draft->grand_total);
    }

    public function test_cross_company_payment_allocation_rejected(): void
    {
        $otherCompany = Company::factory()->create(['code' => 'PGG']);

        $invoice = $this->workflow->issue(
            $this->workflow->createDraft([
                'company_id' => $this->company->id,
                'document_type' => 'invoice',
                'items' => [['description' => 'X', 'quantity' => 1, 'unit_price' => 100]],
            ])->id
        );

        $payment = $this->workflow->recordPayment([
            'company_id' => $otherCompany->id,
            'amount' => 100,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->workflow->allocatePaymentToDocument($payment, $invoice->id, 100);
    }

    public function test_status_history_tracks_full_lifecycle(): void
    {
        $draft = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'items' => $this->createDraftItems(),
        ]);

        $this->assertCount(1, $draft->statusHistory);
        $this->assertEquals(Document::STATUS_DRAFT, $draft->statusHistory->first()->to_status);

        $issued = $this->workflow->issue($draft->id);
        $this->assertCount(2, $issued->fresh()->statusHistory);

        $this->workflow->void($issued->id, 'Done');
        $this->assertCount(3, $issued->fresh()->statusHistory);
    }
}
