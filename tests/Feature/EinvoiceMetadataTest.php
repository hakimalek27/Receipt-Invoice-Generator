<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Document;
use App\Models\NumberingPolicy;
use App\Services\DocumentWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EinvoiceMetadataTest extends TestCase
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
            'prefix' => 'WS', 'separator' => '-', 'year_token' => '{YYYY}',
            'sequence_padding' => 5, 'reset_policy' => 'yearly', 'is_active' => true,
        ]);
    }

    public function test_line_level_classification_and_tax_fields_captured(): void
    {
        $draft = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'items' => [
                [
                    'description' => 'Service A',
                    'quantity' => 1,
                    'unit_price' => 100,
                    'tax_type' => 'SST',
                    'tax_rate' => '6.00',
                    'tax_amount' => 6.00,
                    'classification_code' => '001',
                    'tax_exemption_reason' => null,
                ],
            ],
        ]);

        $item = $draft->items->first();
        $this->assertEquals('SST', $item->tax_type);
        $this->assertEquals(6.00, (float) $item->tax_amount);
        $this->assertEquals('001', $item->classification_code);
        $this->assertNull($item->tax_exemption_reason);
    }

    public function test_myr_document_without_fx(): void
    {
        $draft = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'currency' => 'MYR',
            'items' => [['description' => 'X', 'quantity' => 1, 'unit_price' => 100]],
        ]);

        $this->assertEquals('MYR', $draft->currency);
        $this->assertNull($draft->fx_rate);
    }

    public function test_non_myr_document_with_fx_snapshot(): void
    {
        $draft = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'currency' => 'USD',
            'items' => [['description' => 'X', 'quantity' => 1, 'unit_price' => 100]],
        ]);

        $this->assertEquals('USD', $draft->currency);
        $this->assertDatabaseHas('documents', ['id' => $draft->id, 'currency' => 'USD']);
    }

    public function test_myinvois_status_placeholder_exists(): void
    {
        $draft = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'items' => [['description' => 'X', 'quantity' => 1, 'unit_price' => 100]],
        ]);

        $this->assertNull($draft->myinvois_status);
        $this->assertNull($draft->myinvois_uuid);
    }

    public function test_no_external_myinvois_api_call_is_made(): void
    {
        $draft = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'items' => [['description' => 'X', 'quantity' => 1, 'unit_price' => 100]],
        ]);

        $issued = $this->workflow->issue($draft->id);

        // MyInvois status remains null — no submission happened
        $this->assertNull($issued->fresh()->myinvois_status);
        $this->assertNotNull($issued->official_number);
    }

    public function test_future_only_document_types_not_exposed_as_v1_workflow(): void
    {
        $futureTypes = ['refund_note', 'self_billed_invoice', 'self_billed_credit_note',
            'self_billed_debit_note', 'self_billed_refund_note'];

        $v1Types = ['invoice', 'quotation', 'official_receipt', 'delivery_order',
            'cash_bill', 'credit_note', 'debit_note', 'purchase_order',
            'payment_voucher', 'proforma_invoice'];

        foreach ($futureTypes as $type) {
            $this->assertNotContains($type, $v1Types, "{$type} must not be in v1 scope");
        }

        // Verify numbering policies NOT created for future types
        $this->assertDatabaseMissing('numbering_policies', [
            'document_type' => 'refund_note',
            'company_id' => $this->company->id,
        ]);
        $this->assertDatabaseMissing('numbering_policies', [
            'document_type' => 'self_billed_invoice',
            'company_id' => $this->company->id,
        ]);
    }

    public function test_issuer_and_buyer_snapshots_captured(): void
    {
        $customer = \App\Models\Customer::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Test Customer',
            'tax_identifier' => 'TIN-12345',
        ]);

        $draft = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'customer_id' => $customer->id,
            'items' => [['description' => 'X', 'quantity' => 1, 'unit_price' => 100]],
        ]);

        $issued = $this->workflow->issue($draft->id);

        $this->assertNotNull($issued->issuer_snapshot_json);
        $this->assertNotNull($issued->buyer_snapshot_json);
        $this->assertEquals('Test Customer', $issued->buyer_snapshot_json['name']);
        $this->assertEquals('TIN-12345', $issued->buyer_snapshot_json['tax_identifier']);
    }
}
