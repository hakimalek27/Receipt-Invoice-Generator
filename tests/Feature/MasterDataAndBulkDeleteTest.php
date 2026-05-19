<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\NumberingPolicy;
use App\Models\User;
use App\Services\DocumentWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MasterDataAndBulkDeleteTest extends TestCase
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
        $this->workflow = app(DocumentWorkflowService::class);
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

    public function test_template_delete_endpoint_removes_template(): void
    {
        $template = DocumentTemplate::create([
            'company_id' => $this->company->id,
            'name' => 'Test Template',
            'document_type' => 'invoice',
            'paper_size' => 'A4',
            'is_active' => true,
        ]);

        $this->deleteJson("/api/templates/{$template->id}")->assertOk()
            ->assertJsonPath('deleted', true);
        $this->assertSoftDeleted('document_templates', ['id' => $template->id]);
    }

    public function test_numbering_policy_delete_endpoint_removes_policy(): void
    {
        $policy = NumberingPolicy::create([
            'company_id' => $this->company->id,
            'document_type' => 'quotation',
            'prefix' => 'WS-QUO',
            'separator' => '-',
            'year_token' => '{YYYY}',
            'sequence_padding' => 5,
            'reset_policy' => 'yearly',
            'is_active' => true,
        ]);

        $this->deleteJson("/api/numbering-policies/{$policy->id}")->assertOk()
            ->assertJsonPath('deleted', true);
        $this->assertSoftDeleted('numbering_policies', ['id' => $policy->id]);
    }

    public function test_single_draft_delete_works_but_issued_cannot_delete(): void
    {
        $draft = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'items' => [['description' => 'X', 'quantity' => 1, 'unit_price' => 100]],
        ]);

        $this->deleteJson("/api/documents/{$draft->id}")->assertOk()
            ->assertJsonPath('deleted', true);
        $this->assertSoftDeleted('documents', ['id' => $draft->id]);

        $issued = $this->workflow->issue($this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'items' => [['description' => 'Y', 'quantity' => 1, 'unit_price' => 100]],
        ])->id);

        $this->deleteJson("/api/documents/{$issued->id}")
            ->assertStatus(422)
            ->assertJsonPath('error', 'Only draft documents may be deleted; use void for issued documents.');
    }

    public function test_bulk_delete_drafts_only_removes_drafts_in_user_company(): void
    {
        $draftA = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'items' => [['description' => 'A', 'quantity' => 1, 'unit_price' => 50]],
        ]);
        $draftB = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'items' => [['description' => 'B', 'quantity' => 1, 'unit_price' => 60]],
        ]);
        $issued = $this->workflow->issue($this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'items' => [['description' => 'C', 'quantity' => 1, 'unit_price' => 70]],
        ])->id);

        // Other-company draft should NOT be deleted.
        $otherCompany = Company::factory()->create(['code' => 'OTHER']);
        NumberingPolicy::create([
            'company_id' => $otherCompany->id, 'document_type' => 'invoice',
            'prefix' => 'O-INV', 'separator' => '-', 'year_token' => '{YYYY}',
            'sequence_padding' => 5, 'reset_policy' => 'yearly', 'is_active' => true,
        ]);
        $otherDraft = $this->workflow->createDraft([
            'company_id' => $otherCompany->id,
            'document_type' => 'invoice',
            'items' => [['description' => 'OTHER', 'quantity' => 1, 'unit_price' => 80]],
        ]);

        $response = $this->postJson('/api/documents/bulk-delete-drafts', [
            'ids' => [$draftA->id, $draftB->id, $issued->id, $otherDraft->id],
        ])->assertOk();

        $this->assertSame(2, $response->json('deleted_count'));
        $this->assertSoftDeleted('documents', ['id' => $draftA->id]);
        $this->assertSoftDeleted('documents', ['id' => $draftB->id]);
        $this->assertDatabaseHas('documents', ['id' => $issued->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('documents', ['id' => $otherDraft->id, 'deleted_at' => null]);
    }

    public function test_status_history_appears_after_issue_and_void(): void
    {
        $draft = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'items' => [['description' => 'X', 'quantity' => 1, 'unit_price' => 100]],
        ]);
        $issued = $this->workflow->issue($draft->id, $this->user->id);
        $this->workflow->void($issued->id, 'Customer requested cancellation', $this->user->id);

        $this->actingAs($this->user)->get("/documents/{$issued->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Documents/Edit')
                ->has('statusHistory', 3)
                ->where('statusHistory.0.to_status', 'draft')
                ->where('statusHistory.1.to_status', 'issued')
                ->where('statusHistory.2.to_status', 'void')
                ->where('statusHistory.2.reason', 'Customer requested cancellation')
            );
    }
}
