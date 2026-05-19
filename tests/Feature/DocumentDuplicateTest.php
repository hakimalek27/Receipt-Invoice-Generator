<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\NumberingPolicy;
use App\Models\User;
use App\Services\DocumentWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentDuplicateTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private DocumentWorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->wehdah()->create();
        $this->user = User::factory()->create([
            'role' => 'admin',
            'company_id' => $this->company->id,
        ]);
        NumberingPolicy::create([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'prefix' => 'WS-INV',
            'separator' => '-',
            'year_token' => '{YYYY}',
            'sequence_padding' => 5,
            'reset_policy' => 'yearly',
            'is_active' => true,
        ]);
        $this->workflow = app(DocumentWorkflowService::class);
        Sanctum::actingAs($this->user);
    }

    public function test_duplicate_creates_independent_draft_with_items_no_chain_link(): void
    {
        $customer = Customer::create([
            'company_id' => $this->company->id,
            'name' => 'Acme',
            'is_active' => true,
        ]);
        $original = $this->workflow->issue($this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'customer_id' => $customer->id,
            'terms' => 'Net 14',
            'currency' => 'MYR',
            'items' => [
                ['description' => 'Item A', 'quantity' => 2, 'unit_price' => 100],
                ['description' => 'Item B', 'quantity' => 1, 'unit_price' => 50],
            ],
        ])->id);

        $response = $this->postJson("/api/documents/{$original->id}/duplicate")
            ->assertCreated()
            ->json();

        $newDoc = Document::with('items')->find($response['id']);
        $this->assertSame(Document::STATUS_DRAFT, $newDoc->status);
        $this->assertNull($newDoc->official_number);
        $this->assertNull($newDoc->converted_from_id);
        $this->assertNull($newDoc->due_date);
        $this->assertSame(now()->toDateString(), $newDoc->document_date->toDateString());
        $this->assertSame($customer->id, $newDoc->customer_id);
        $this->assertSame('Net 14', $newDoc->terms);
        $this->assertCount(2, $newDoc->items);
        $this->assertSame((float) $original->grand_total, (float) $newDoc->grand_total);
    }

    public function test_duplicate_blocked_for_cross_company_source(): void
    {
        $otherCompany = Company::factory()->create(['code' => 'OTHER']);
        NumberingPolicy::create([
            'company_id' => $otherCompany->id,
            'document_type' => 'invoice',
            'prefix' => 'O-INV',
            'separator' => '-',
            'year_token' => '{YYYY}',
            'sequence_padding' => 5,
            'reset_policy' => 'yearly',
            'is_active' => true,
        ]);
        $foreignDoc = $this->workflow->createDraft([
            'company_id' => $otherCompany->id,
            'document_type' => 'invoice',
            'items' => [['description' => 'Secret', 'quantity' => 1, 'unit_price' => 999]],
        ]);

        $this->postJson("/api/documents/{$foreignDoc->id}/duplicate")
            ->assertForbidden();
    }
}
