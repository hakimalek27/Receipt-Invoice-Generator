<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\NumberingPolicy;
use App\Models\User;
use App\Services\DocumentWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentsDateFilterTest extends TestCase
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
    }

    public function test_documents_web_route_respects_date_from_and_date_to(): void
    {
        $jan = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'document_date' => '2025-01-15',
            'items' => [['description' => 'Jan', 'quantity' => 1, 'unit_price' => 100]],
        ]);
        $jun = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'document_date' => '2025-06-15',
            'items' => [['description' => 'Jun', 'quantity' => 1, 'unit_price' => 200]],
        ]);
        $dec = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'document_date' => '2025-12-15',
            'items' => [['description' => 'Dec', 'quantity' => 1, 'unit_price' => 300]],
        ]);

        $this->actingAs($this->user)->get('/documents?date_from=2025-06-01&date_to=2025-06-30')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Documents/Index')
                ->has('documents.data', 1)
                ->where('documents.data.0.id', $jun->id)
                ->where('filters.date_from', '2025-06-01')
                ->where('filters.date_to', '2025-06-30')
            );
    }

    public function test_documents_web_route_combines_date_with_status_filter(): void
    {
        $draftJun = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'document_date' => '2025-06-10',
            'items' => [['description' => 'Draft Jun', 'quantity' => 1, 'unit_price' => 100]],
        ]);
        $issuedJun = $this->workflow->issue($this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'document_date' => '2025-06-20',
            'items' => [['description' => 'Issued Jun', 'quantity' => 1, 'unit_price' => 200]],
        ])->id);
        $draftDec = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'document_date' => '2025-12-01',
            'items' => [['description' => 'Draft Dec', 'quantity' => 1, 'unit_price' => 300]],
        ]);

        $this->actingAs($this->user)->get('/documents?status=draft&date_from=2025-06-01&date_to=2025-06-30')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Documents/Index')
                ->has('documents.data', 1)
                ->where('documents.data.0.id', $draftJun->id)
            );
    }
}
