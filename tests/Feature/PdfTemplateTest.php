<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\User;
use App\Services\DocumentWorkflowService;
use App\Services\PdfRenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PdfTemplateTest extends TestCase
{
    use RefreshDatabase;

    private DocumentWorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workflow = app(DocumentWorkflowService::class);
    }

    public function test_all_supported_pdf_views_exist(): void
    {
        foreach ([
            'pdf.wehdah.invoice',
            'pdf.wehdah.quotation',
            'pdf.nasceria.invoice',
            'pdf.nasceria.quotation',
            'pdf.persada.invoice',
            'pdf.generic.invoice',
            'pdf.generic.quotation',
            'pdf.generic.official_receipt',
            'pdf.generic.delivery_order',
            'pdf.generic.cash_bill',
            'pdf.generic.credit_note',
            'pdf.generic.debit_note',
            'pdf.generic.purchase_order',
            'pdf.generic.payment_voucher',
            'pdf.generic.proforma_invoice',
            'pdf.thermal_receipt',
        ] as $view) {
            $this->assertTrue(view()->exists($view), "{$view} is missing");
        }
    }

    public function test_render_every_supported_company_document_template_without_crash(): void
    {
        Storage::fake('local');
        $service = app(PdfRenderService::class);

        $cases = [
            ['WS', 'invoice'],
            ['WS', 'quotation'],
            ['NCS', 'invoice'],
            ['NCS', 'quotation'],
            ['PGG', 'invoice'],
            ['GEN', 'official_receipt'],
            ['GEN', 'delivery_order'],
            ['GEN', 'cash_bill'],
            ['GEN', 'credit_note'],
            ['GEN', 'debit_note'],
            ['GEN', 'purchase_order'],
            ['GEN', 'payment_voucher'],
            ['GEN', 'proforma_invoice'],
        ];

        $companies = [
            'WS' => Company::factory()->create(['code' => 'WS']),
            'NCS' => Company::factory()->create(['code' => 'NCS']),
            'PGG' => Company::factory()->create(['code' => 'PGG']),
            'GEN' => Company::factory()->create(['code' => 'GEN']),
        ];

        foreach ($cases as [$code, $type]) {
            $company = $companies[$code];
            $document = $this->draft($company, $type, 2);

            $render = $service->render($document);

            $this->assertSame($document->id, $render->document_id);
            $this->assertGreaterThan(0, $render->file_size, "{$code} {$type} rendered empty PDF");
            Storage::disk('local')->assertExists($render->file_path);
        }
    }

    public function test_multipage_render_data_keeps_totals_and_amount_words_on_final_page_only(): void
    {
        $company = Company::factory()->wehdah()->create();
        $document = $this->draft($company, 'invoice', 60, [
            'show_amount_in_words' => true,
            'amount_in_words_locale' => 'ms_MY',
        ]);

        $data = app(PdfRenderService::class)->renderData($document);
        $html = view('pdf.wehdah.invoice', $data)->render();

        $this->assertCount(4, $data['itemPages']);
        $this->assertStringContainsString('Muka 4 / 4', $html);
        $this->assertSame(1, substr_count($html, 'RINGGIT MALAYSIA'));
        $this->assertSame(1, substr_count($html, 'JUMLAH BESAR'));
    }

    public function test_wehdah_artwork_pages_render_after_main_document(): void
    {
        Storage::fake('local');
        $company = Company::factory()->wehdah()->create();
        $document = $this->draft($company, 'invoice', 1);
        $path = "documents/{$company->id}/{$document->id}/attachments/artwork.png";
        Storage::disk('local')->put($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADggGOSHzRgAAAAABJRU5ErkJggg=='
        ));

        DocumentAttachment::create([
            'company_id' => $company->id,
            'document_id' => $document->id,
            'original_name' => 'artwork.png',
            'storage_path' => $path,
            'mime_type' => 'image/png',
            'size_bytes' => 67,
            'caption' => 'Artwork confirmation',
            'sort_order' => 1,
            'include_in_pdf' => true,
        ]);

        $html = view('pdf.wehdah.invoice', app(PdfRenderService::class)->renderData($document))->render();

        $this->assertStringContainsString('Artwork 1', $html);
        $this->assertStringContainsString('Artwork confirmation', $html);
        $this->assertGreaterThan(strpos($html, 'JUMLAH BESAR'), strpos($html, 'Artwork 1'));
        $this->assertStringContainsString('data:image/png;base64,', $html);
    }

    public function test_thermal_receipt_uses_dynamic_non_zero_height(): void
    {
        $company = Company::factory()->create(['code' => 'WS']);
        $document = $this->draft($company, 'official_receipt', 30);
        $service = app(PdfRenderService::class);

        $method = new \ReflectionMethod($service, 'thermalPaperBox');
        $box = $method->invoke($service, $document);

        $this->assertSame(170.08, $box[2]);
        $this->assertGreaterThan(0, $box[3]);
    }

    public function test_attachment_upload_rejects_svg_and_path_traversal_names(): void
    {
        Storage::fake('local');
        $company = Company::factory()->create(['code' => 'WS']);
        $user = User::factory()->create(['role' => 'admin', 'company_id' => $company->id]);
        Sanctum::actingAs($user);

        $document = $this->draft($company, 'invoice', 1);

        $this->postJson("/api/documents/{$document->id}/attachments", [
            'file' => UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml'),
        ])->assertUnprocessable();

        $pathTraversalFile = tempnam(sys_get_temp_dir(), 'bad-upload');
        file_put_contents($pathTraversalFile, '%PDF-1.4 path traversal probe');

        $this->postJson("/api/documents/{$document->id}/attachments", [
            'file' => new class($pathTraversalFile) extends UploadedFile
            {
                public function __construct(string $path)
                {
                    parent::__construct($path, 'bad.pdf', 'application/pdf', null, true);
                }

                public function getClientOriginalName(): string
                {
                    return '..\\bad.pdf';
                }
            },
        ])->assertUnprocessable();

        $this->postJson("/api/documents/{$document->id}/attachments", [
            'file' => UploadedFile::fake()->image('artwork.png', 20, 20),
            'caption' => 'Safe artwork',
        ])->assertCreated()
            ->assertJsonPath('caption', 'Safe artwork');
    }

    private function draft(Company $company, string $type, int $itemCount, array $overrides = []): Document
    {
        $items = collect(range(1, $itemCount))->map(fn ($i) => [
            'description' => $i === 1
                ? 'Long signboard item description for wrapping and pagination verification'
                : "Item {$i}",
            'quantity' => 1,
            'unit_price' => 10 + $i,
            'sort_order' => $i,
        ])->all();

        return $this->workflow->createDraft(array_merge([
            'company_id' => $company->id,
            'document_type' => $type,
            'items' => $items,
        ], $overrides));
    }
}
