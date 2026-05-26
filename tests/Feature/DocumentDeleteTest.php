<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentStatusHistory;
use App\Models\NumberingPolicy;
use App\Models\User;
use App\Services\DocumentWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentDeleteTest extends TestCase
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

        foreach (['invoice' => 'INV', 'quotation' => 'Q', 'delivery_order' => 'DO'] as $type => $short) {
            NumberingPolicy::create([
                'company_id' => $this->company->id,
                'document_type' => $type,
                'prefix' => 'WS-'.$short,
                'separator' => '-',
                'year_token' => '{YYYY}',
                'sequence_padding' => 5,
                'reset_policy' => 'yearly',
                'is_active' => true,
            ]);
        }
    }

    private function issueInvoice(): Document
    {
        $draft = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'document_date' => '2026-01-15',
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 250]],
        ]);

        return $this->workflow->issue($draft->id, $this->user->id, $draft->draft_hash, 250.00);
    }

    public function test_issued_doc_can_be_deleted(): void
    {
        $doc = $this->issueInvoice();
        Sanctum::actingAs($this->user);

        $this->deleteJson("/api/documents/{$doc->id}")
            ->assertOk()
            ->assertJson([
                'deleted' => true,
                'recycled_number' => $doc->official_number,
                'previous_status' => 'issued',
            ]);

        // Soft-deleted: row still exists with deleted_at set.
        $this->assertNotNull(Document::withTrashed()->find($doc->id)->deleted_at);

        // Status history entry logged.
        $this->assertTrue(
            DocumentStatusHistory::where('document_id', $doc->id)
                ->where('to_status', 'deleted')
                ->exists(),
            'A status-history row with to_status=deleted should be logged.'
        );
    }

    public function test_delete_blocked_by_children(): void
    {
        $quote = $this->workflow->issue($this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'quotation',
            'document_date' => '2026-01-15',
            'items' => [['description' => 'Quote item', 'quantity' => 1, 'unit_price' => 500]],
        ])->id, $this->user->id, null, 500.00);

        // Derive a child invoice from the quote.
        $this->workflow->derive($quote->id, 'invoice', []);

        Sanctum::actingAs($this->user);

        $response = $this->deleteJson("/api/documents/{$quote->id}")
            ->assertStatus(422)
            ->assertJson(['children_count' => 1]);

        $this->assertStringContainsString('Cannot delete', $response->json('error'));
        $this->assertStringContainsString('Delete those first', $response->json('error'));

        // Source must NOT be soft-deleted after the failed attempt.
        $this->assertNull(Document::find($quote->id)?->deleted_at);
    }

    public function test_delete_recycles_official_number_for_next_issue(): void
    {
        $first = $this->issueInvoice();
        $second = $this->issueInvoice();
        $this->assertEquals('WS-INV-2026-00001', $first->official_number);
        $this->assertEquals('WS-INV-2026-00002', $second->official_number);

        Sanctum::actingAs($this->user);
        $this->deleteJson("/api/documents/{$first->id}")->assertOk();

        // Next issue should re-use 00001.
        $next = $this->issueInvoice();
        $this->assertEquals('WS-INV-2026-00001', $next->official_number);
    }

    public function test_bulk_delete_mixed_statuses_with_blocked(): void
    {
        $clean = $this->issueInvoice();         // can be deleted
        $draft = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'document_date' => '2026-01-15',
            'items' => [['description' => 'Draft item', 'quantity' => 1, 'unit_price' => 50]],
        ]);
        $sourceWithChild = $this->workflow->issue($this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'quotation',
            'document_date' => '2026-01-15',
            'items' => [['description' => 'Quote', 'quantity' => 1, 'unit_price' => 999]],
        ])->id, $this->user->id, null, 999.00);
        $this->workflow->derive($sourceWithChild->id, 'invoice', []);

        Sanctum::actingAs($this->user);

        $resp = $this->postJson('/api/documents/bulk-delete-drafts', [
            'ids' => [$clean->id, $draft->id, $sourceWithChild->id],
        ])->assertOk()->json();

        $this->assertSame(2, $resp['deleted_count'], 'Clean + draft should be deleted.');
        $this->assertCount(1, $resp['blocked'], 'Source-with-child must be blocked.');
        $this->assertSame($sourceWithChild->id, $resp['blocked'][0]['id']);
    }
}
