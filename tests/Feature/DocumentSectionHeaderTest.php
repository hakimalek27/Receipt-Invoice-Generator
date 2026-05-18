<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Services\DocumentWorkflowService;
use App\Services\PdfRenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentSectionHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_section_header_row_renders_as_span_all_heading_for_every_company(): void
    {
        $workflow = app(DocumentWorkflowService::class);
        $service = app(PdfRenderService::class);

        $companies = [
            'WS' => Company::factory()->wehdah()->create(),
            'NCS' => Company::factory()->nasCeria()->create(),
            'PGG' => Company::factory()->persada()->create(),
            'VD' => Company::factory()->virtueDamsel()->create(),
        ];

        foreach ($companies as $code => $company) {
            $document = $workflow->createDraft([
                'company_id' => $company->id,
                'document_type' => 'invoice',
                'items' => [
                    ['description' => 'Pasang siling', 'section_header' => 'Bilik Muaazzin', 'quantity' => 1, 'unit_price' => 100],
                    ['description' => 'Ganti lampu', 'quantity' => 2, 'unit_price' => 25],
                ],
            ]);

            $template = match ($code) {
                'WS' => 'pdf.wehdah.invoice',
                'NCS' => 'pdf.nasceria.invoice',
                'PGG' => 'pdf.persada.invoice',
                'VD' => 'pdf.virtuedamsel.invoice',
            };

            $html = view($template, $service->renderData($document))->render();

            $this->assertStringContainsString('section-header-row', $html, "{$code}: section-header-row class missing");
            $this->assertStringContainsString('Bilik Muaazzin', $html, "{$code}: section header text missing");
            $this->assertStringContainsString('colspan="7"', $html, "{$code}: section row not full-span");
        }
    }

    public function test_section_header_absent_when_field_is_null(): void
    {
        $workflow = app(DocumentWorkflowService::class);
        $service = app(PdfRenderService::class);
        $company = Company::factory()->wehdah()->create();

        $document = $workflow->createDraft([
            'company_id' => $company->id,
            'document_type' => 'invoice',
            'items' => [
                ['description' => 'Regular item', 'quantity' => 1, 'unit_price' => 50],
            ],
        ]);

        $html = view('pdf.wehdah.invoice', $service->renderData($document))->render();

        $this->assertStringNotContainsString('section-header-row', $html);
    }
}
