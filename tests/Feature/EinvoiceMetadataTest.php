<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Document;
use App\Models\NumberingPolicy;
use App\Models\User;
use App\Services\DocumentWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
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

    public function test_non_myr_document_requires_fx_snapshot(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Non-MYR documents require an FX rate snapshot');

        $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'currency' => 'USD',
            'items' => [['description' => 'X', 'quantity' => 1, 'unit_price' => 100]],
        ]);
    }

    public function test_non_myr_document_with_fx_snapshot(): void
    {
        $draft = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'currency' => 'USD',
            'fx_rate' => 4.70000000,
            'items' => [['description' => 'X', 'quantity' => 1, 'unit_price' => 100]],
        ]);

        $this->assertEquals('USD', $draft->currency);
        $this->assertEquals(4.7, (float) $draft->fx_rate);
        $this->assertDatabaseHas('documents', ['id' => $draft->id, 'currency' => 'USD']);
    }

    public function test_api_accepts_einvoice_line_metadata_and_rejects_missing_fx(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'company_id' => $this->company->id,
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/documents', [
            'document_type' => 'invoice',
            'currency' => 'USD',
            'items' => [['description' => 'X', 'quantity' => 1, 'unit_price' => 100]],
        ])->assertStatus(422)
            ->assertJson(['error' => 'Non-MYR documents require an FX rate snapshot']);

        $response = $this->postJson('/api/documents', [
            'document_type' => 'invoice',
            'currency' => 'USD',
            'fx_rate' => 4.7,
            'items' => [[
                'description' => 'Taxable design service',
                'quantity' => 1,
                'unit_price' => 100,
                'tax_type' => 'SST',
                'tax_rate' => 6,
                'tax_amount' => 6,
                'classification_code' => '022',
                'tax_exemption_reason' => 'N/A',
            ]],
        ])->assertCreated();

        $this->assertEquals('022', $response->json('items.0.classification_code'));
        $this->assertEquals('N/A', $response->json('items.0.tax_exemption_reason'));
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
        $this->company->update([
            'tin' => 'C25845632020',
            'sst_registration_number' => 'W10-2401-32000001',
            'msic_code' => '18110',
            'business_activity_description' => 'Printing and signage services',
            'address_line_2' => 'Level 2',
            'city' => 'Kuala Lumpur',
            'state' => 'Wilayah Persekutuan Kuala Lumpur',
            'postcode' => '53300',
            'country' => 'MY',
        ]);

        $customer = \App\Models\Customer::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Test Customer',
            'tax_identifier' => 'TIN-12345',
            'brn_registration_number' => '202001234567',
            'sst_registration_number' => 'B16-1808-32000099',
            'msic_code' => '62010',
            'city' => 'Petaling Jaya',
            'state' => 'Selangor',
            'postcode' => '46000',
            'country' => 'MY',
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
        $this->assertEquals('C25845632020', $issued->issuer_snapshot_json['tin']);
        $this->assertEquals('18110', $issued->issuer_snapshot_json['msic_code']);
        $this->assertStringContainsString('53300', $issued->issuer_snapshot_json['canonical_address']);
        $this->assertEquals('Test Customer', $issued->buyer_snapshot_json['name']);
        $this->assertEquals('TIN-12345', $issued->buyer_snapshot_json['tax_identifier']);
        $this->assertEquals('202001234567', $issued->buyer_snapshot_json['brn_registration_number']);
        $this->assertEquals('B16-1808-32000099', $issued->buyer_snapshot_json['sst_registration_number']);
    }

    public function test_master_data_accepts_einvoice_metadata_fields(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'company_id' => $this->company->id,
        ]);
        Sanctum::actingAs($user);

        $this->patchJson("/api/companies/{$this->company->id}", [
            'tin' => 'C25845632020',
            'sst_registration_number' => 'W10-2401-32000001',
            'msic_code' => '18110',
            'business_activity_description' => 'Printing services',
            'city' => 'Kuala Lumpur',
            'country' => 'MY',
        ])->assertOk()
            ->assertJsonPath('tin', 'C25845632020')
            ->assertJsonPath('msic_code', '18110');

        $this->postJson('/api/customers', [
            'name' => 'Buyer A',
            'tax_identifier' => 'EI00000000010',
            'brn_registration_number' => '202001234567',
            'sst_registration_number' => 'NA',
            'msic_code' => '62010',
            'city' => 'Kuala Lumpur',
            'country' => 'MY',
        ])->assertCreated()
            ->assertJsonPath('tax_identifier', 'EI00000000010')
            ->assertJsonPath('brn_registration_number', '202001234567');
    }

    public function test_no_myinvois_submission_route_or_job_exists_in_v1(): void
    {
        $routes = collect(Route::getRoutes())->map(fn ($route) => $route->uri())->all();

        $this->assertFalse(collect($routes)->contains(fn ($uri) => str_contains($uri, 'myinvois')));
        $this->assertFalse(collect($routes)->contains(fn ($uri) => str_contains($uri, 'submit')));
        $this->assertDirectoryDoesNotExist(app_path('Jobs/MyInvois'));
    }
}
