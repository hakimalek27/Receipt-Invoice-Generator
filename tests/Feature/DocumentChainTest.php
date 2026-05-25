<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\NumberingPolicy;
use App\Models\User;
use App\Services\DocumentWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DocumentChainTest extends TestCase
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

        foreach (['quotation' => 'Q', 'invoice' => 'INV', 'delivery_order' => 'DO'] as $type => $code) {
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

    public function test_derive_links_source_via_converted_from_id(): void
    {
        $quote = $this->workflow->issue($this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'quotation',
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 200]],
        ])->id);

        $invoice = $this->workflow->derive($quote->id, 'invoice', []);

        $this->assertSame($quote->id, $invoice->converted_from_id);
        $this->assertSame('invoice', $invoice->document_type);
    }

    public function test_derive_keeps_source_as_issued(): void
    {
        $quote = $this->workflow->issue($this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'quotation',
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 200]],
        ])->id);

        $this->workflow->derive($quote->id, 'invoice', []);
        $quote->refresh();

        $this->assertSame('issued', $quote->status, 'Source must remain issued after derive (no more "converted" status).');
    }

    public function test_multiple_derives_from_one_source(): void
    {
        $quote = $this->workflow->issue($this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'quotation',
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 200]],
        ])->id);

        $first = $this->workflow->derive($quote->id, 'invoice', []);
        $second = $this->workflow->derive($quote->id, 'invoice', []);

        $quote->refresh();
        $this->assertSame('issued', $quote->status);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame($quote->id, $first->converted_from_id);
        $this->assertSame($quote->id, $second->converted_from_id);
        $this->assertSame(2, $quote->convertedTo()->count(), 'Source should now have two derived children.');
    }

    public function test_inertia_payload_includes_chain_relations(): void
    {
        $quote = $this->workflow->issue($this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'quotation',
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 200]],
        ])->id);
        $invoice = $this->workflow->derive($quote->id, 'invoice', []);

        $this->actingAs($this->user)
            ->get("/documents/{$invoice->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Documents/Edit')
                ->has('document.converted_from', fn (Assert $cf) => $cf
                    ->where('id', $quote->id)
                    ->where('document_type', 'quotation')
                    ->etc()
                )
            );

        $this->actingAs($this->user)
            ->get("/documents/{$quote->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Documents/Edit')
                ->has('document.converted_to', 1, fn (Assert $child) => $child
                    ->where('id', $invoice->id)
                    ->where('document_type', 'invoice')
                    ->etc()
                )
            );
    }

    public function test_invalid_derivation_target_returns_422(): void
    {
        $invoice = $this->workflow->issue($this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 200]],
        ])->id);

        \Laravel\Sanctum\Sanctum::actingAs($this->user);

        $this->postJson("/api/documents/{$invoice->id}/convert", ['target_type' => 'quotation'])
            ->assertStatus(422);
    }
}
