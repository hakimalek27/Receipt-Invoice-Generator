<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DocumentItem;
use App\Services\DocumentWorkflowService;
use App\Services\PdfRenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentMarginTest extends TestCase
{
    use RefreshDatabase;

    public function test_margin_accessor_returns_revenue_minus_cost_times_quantity(): void
    {
        $item = new DocumentItem([
            'unit_price' => 100,
            'cost_unit' => 70,
            'quantity' => 3,
        ]);

        $this->assertSame(90.0, $item->margin_amount);
    }

    public function test_margin_accessor_returns_null_when_cost_unit_unset(): void
    {
        $item = new DocumentItem([
            'unit_price' => 100,
            'quantity' => 3,
        ]);

        $this->assertNull($item->margin_amount);
    }

    public function test_margin_data_never_leaks_into_rendered_pdf_html(): void
    {
        $workflow = app(DocumentWorkflowService::class);
        $service = app(PdfRenderService::class);

        foreach (['WS', 'NCS', 'PGG', 'VD'] as $code) {
            $company = Company::factory()->state(['code' => $code])->create();
            $document = $workflow->createDraft([
                'company_id' => $company->id,
                'document_type' => 'invoice',
                'items' => [
                    ['description' => 'Confidential cost item', 'quantity' => 2, 'unit_price' => 100, 'cost_unit' => 60],
                ],
            ]);

            $template = match ($code) {
                'WS' => 'pdf.wehdah.invoice',
                'NCS' => 'pdf.nasceria.invoice',
                'PGG' => 'pdf.persada.invoice',
                'VD' => 'pdf.virtuedamsel.invoice',
            };
            $html = view($template, $service->renderData($document))->render();

            $this->assertStringNotContainsString('cost_unit', $html, "{$code}: cost_unit field leaked");
            $this->assertStringNotContainsString('Margin', $html, "{$code}: Margin label leaked");
            $this->assertStringNotContainsString('60.00', $html, "{$code}: cost value 60.00 leaked");
        }
    }
}
