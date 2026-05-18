<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Services\DocumentWorkflowService;
use App\Services\PdfRenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentArabicSalutationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pgg_invoice_renders_bismillah_block_when_toggle_on(): void
    {
        $workflow = app(DocumentWorkflowService::class);
        $service = app(PdfRenderService::class);
        $company = Company::factory()->persada()->create();

        $document = $workflow->createDraft([
            'company_id' => $company->id,
            'document_type' => 'invoice',
            'include_arabic_salutation' => true,
            'items' => [['description' => 'Sponsorship pack', 'quantity' => 1, 'unit_price' => 500]],
        ]);

        $html = view('pdf.persada.invoice', $service->renderData($document))->render();

        $this->assertStringContainsString('بِسْمِ', $html);
        $this->assertStringContainsString('Dengan nama Allah', $html);
        $this->assertStringContainsString('السَّلامُ عَلَيْكُمْ', $html);
    }

    public function test_pgg_invoice_omits_bismillah_when_toggle_off(): void
    {
        $workflow = app(DocumentWorkflowService::class);
        $service = app(PdfRenderService::class);
        $company = Company::factory()->persada()->create();

        $document = $workflow->createDraft([
            'company_id' => $company->id,
            'document_type' => 'invoice',
            'include_arabic_salutation' => false,
            'items' => [['description' => 'Sponsorship pack', 'quantity' => 1, 'unit_price' => 500]],
        ]);

        $html = view('pdf.persada.invoice', $service->renderData($document))->render();

        $this->assertStringNotContainsString('بِسْمِ', $html);
        $this->assertStringNotContainsString('Dengan nama Allah', $html);
    }

    public function test_scentury_toggle_renders_gold_accent(): void
    {
        $workflow = app(DocumentWorkflowService::class);
        $service = app(PdfRenderService::class);
        $company = Company::factory()->persada()->create();

        $document = $workflow->createDraft([
            'company_id' => $company->id,
            'document_type' => 'invoice',
            'product_line' => 'scentury',
            'items' => [['description' => 'SCENTURY hamper', 'quantity' => 1, 'unit_price' => 1200]],
        ]);

        $html = view('pdf.persada.invoice', $service->renderData($document))->render();

        $this->assertStringContainsString('SCENTURY by', $html);
        $this->assertStringContainsString('#D4AF37', $html);
    }
}
