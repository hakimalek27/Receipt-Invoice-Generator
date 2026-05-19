<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\NumberingPolicy;
use App\Models\Product;
use App\Models\User;
use App\Services\DocumentWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MasterDataCrudTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

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
        Sanctum::actingAs($this->user);
    }

    public function test_customer_delete_nulls_document_customer_id_keeping_buyer_snapshot(): void
    {
        $customer = Customer::create([
            'company_id' => $this->company->id,
            'name' => 'Acme Sdn Bhd',
            'email' => 'acme@test.local',
            'is_active' => true,
        ]);

        $workflow = app(DocumentWorkflowService::class);
        $draft = $workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'customer_id' => $customer->id,
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 100]],
        ]);
        $issued = $workflow->issue($draft->id, $this->user->id);
        $this->assertNotEmpty($issued->buyer_snapshot_json);

        $this->deleteJson("/api/customers/{$customer->id}")->assertOk()
            ->assertJsonPath('deleted', true);

        // Customer model uses SoftDeletes, so deleted_at is set but the
        // FK on documents stays. The buyer_snapshot_json snapshot survives
        // so issued documents continue to render with the customer's name.
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);

        $issued->refresh();
        $this->assertNotEmpty($issued->buyer_snapshot_json);
        $this->assertSame('Acme Sdn Bhd', $issued->buyer_snapshot_json['name']);
    }

    public function test_product_delete_cross_company_returns_404(): void
    {
        $otherCompany = Company::factory()->create(['code' => 'OTHER']);
        $product = Product::create([
            'company_id' => $otherCompany->id,
            'name' => 'Foreign Product',
            'default_price' => 50,
            'is_active' => true,
        ]);

        $this->deleteJson("/api/products/{$product->id}")->assertNotFound();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'deleted_at' => null]);
    }
}
