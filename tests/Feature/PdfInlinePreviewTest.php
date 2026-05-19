<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\NumberingPolicy;
use App\Models\User;
use App\Services\DocumentWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PdfInlinePreviewTest extends TestCase
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

    private function issuedDocumentId(): int
    {
        $draft = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'items' => [['description' => 'Inline preview test', 'quantity' => 1, 'unit_price' => 10]],
        ]);

        return $this->workflow->issue($draft->id)->id;
    }

    public function test_pdf_endpoint_serves_inline_disposition_by_default(): void
    {
        $id = $this->issuedDocumentId();

        $response = $this->get("/api/documents/{$id}/pdf");

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith(
            'inline;',
            $response->headers->get('content-disposition'),
            'Preview should render in-browser, not trigger save-as.'
        );
    }

    public function test_pdf_endpoint_forces_attachment_when_download_param_set(): void
    {
        $id = $this->issuedDocumentId();

        $response = $this->get("/api/documents/{$id}/pdf?download=1");

        $response->assertOk();
        $this->assertStringStartsWith(
            'attachment;',
            $response->headers->get('content-disposition'),
            '?download=1 should trigger save-as.'
        );
    }

    public function test_inline_response_includes_clickjacking_protection_header(): void
    {
        $id = $this->issuedDocumentId();

        $this->get("/api/documents/{$id}/pdf")
            ->assertHeader('x-frame-options', 'SAMEORIGIN');
    }

    public function test_thermal_60mm_paper_size_also_serves_inline(): void
    {
        $id = $this->issuedDocumentId();

        $response = $this->get("/api/documents/{$id}/pdf?paper=60mm");

        $response->assertOk();
        $this->assertStringStartsWith('inline;', $response->headers->get('content-disposition'));
    }
}
