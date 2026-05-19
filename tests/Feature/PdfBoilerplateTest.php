<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\NumberingPolicy;
use App\Models\User;
use App\Services\DocumentWorkflowService;
use App\Services\PdfBoilerplateService;
use App\Services\PdfRenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PdfBoilerplateTest extends TestCase
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

        foreach (['invoice', 'quotation', 'delivery_order', 'official_receipt'] as $type) {
            NumberingPolicy::create([
                'company_id' => $this->company->id,
                'document_type' => $type,
                'prefix' => 'WS-'.strtoupper(substr($type, 0, 3)),
                'separator' => '-',
                'year_token' => '{YYYY}',
                'sequence_padding' => 5,
                'reset_policy' => 'yearly',
                'is_active' => true,
            ]);
        }
    }

    public function test_default_boilerplate_is_used_when_company_has_no_override(): void
    {
        $document = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 100]],
        ]);
        $html = view('pdf.wehdah.invoice', app(PdfRenderService::class)->renderData($document))->render();

        $this->assertStringContainsString('Goods sold are not returnable', $html);
        $this->assertStringContainsString('Yours faithfully,', $html);
        $this->assertStringContainsString('Authorised Signature', $html);
        $this->assertStringContainsString('Company Sign &amp; Chop', $html);
    }

    public function test_company_override_replaces_default_boilerplate(): void
    {
        $this->company->update([
            'pdf_boilerplate' => [
                'invoice' => [
                    'footer_terms' => 'CUSTOM TERMS: Please pay within 7 days.',
                    'signature_left_intro' => 'Salam hormat,',
                    'signature_left_label' => 'Pengurus Akaun',
                    'signature_right_intro' => 'Barang diterima dengan sempurna,',
                    'signature_right_label' => 'Cop Syarikat Pelanggan',
                ],
            ],
        ]);

        $document = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 100]],
        ]);
        $html = view('pdf.wehdah.invoice', app(PdfRenderService::class)->renderData($document))->render();

        $this->assertStringContainsString('CUSTOM TERMS: Please pay within 7 days.', $html);
        $this->assertStringContainsString('Salam hormat,', $html);
        $this->assertStringContainsString('Pengurus Akaun', $html);
        $this->assertStringContainsString('Cop Syarikat Pelanggan', $html);
        $this->assertStringNotContainsString('Goods sold are not returnable', $html);
        $this->assertStringNotContainsString('Yours faithfully,', $html);
    }

    public function test_company_name_token_is_replaced_in_boilerplate(): void
    {
        $this->company->update([
            'name' => 'My Custom Trading',
            'pdf_boilerplate' => [
                'invoice' => [
                    'footer_terms' => 'All cheques made payable to {company_name} only.',
                ],
            ],
        ]);
        $document = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 100]],
        ]);
        $html = view('pdf.wehdah.invoice', app(PdfRenderService::class)->renderData($document))->render();

        $this->assertStringContainsString('MY CUSTOM TRADING', $html);
        $this->assertStringNotContainsString('{company_name}', $html);
    }

    public function test_quotation_intro_override_applies(): void
    {
        $this->company->update([
            'pdf_boilerplate' => [
                'quotation' => ['intro' => 'Salam sejahtera. Berikut adalah sebut harga kami:'],
            ],
        ]);
        $document = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'quotation',
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 100]],
        ]);
        $html = view('pdf.wehdah.quotation', app(PdfRenderService::class)->renderData($document))->render();

        $this->assertStringContainsString('Salam sejahtera. Berikut adalah sebut harga kami:', $html);
        $this->assertStringNotContainsString('Thank you for your inquiry', $html);
    }

    public function test_blank_override_falls_back_to_default(): void
    {
        $this->company->update([
            'pdf_boilerplate' => [
                'invoice' => [
                    'footer_terms' => '',
                    'signature_left_intro' => null,
                ],
            ],
        ]);
        $document = $this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 100]],
        ]);
        $html = view('pdf.wehdah.invoice', app(PdfRenderService::class)->renderData($document))->render();

        $this->assertStringContainsString('Goods sold are not returnable', $html);
        $this->assertStringContainsString('Yours faithfully,', $html);
    }

    public function test_master_data_api_persists_boilerplate(): void
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'pdf_boilerplate' => [
                'invoice' => [
                    'footer_terms' => 'Bayaran dalam 14 hari sahaja.',
                    'signature_left_intro' => 'Yang ikhlas,',
                ],
                'quotation' => [
                    'intro' => 'Berikut adalah sebut harga kami.',
                ],
            ],
        ];

        $this->patchJson("/api/companies/{$this->company->id}", $payload)
            ->assertOk()
            ->assertJsonPath('pdf_boilerplate.invoice.footer_terms', 'Bayaran dalam 14 hari sahaja.')
            ->assertJsonPath('pdf_boilerplate.invoice.signature_left_intro', 'Yang ikhlas,')
            ->assertJsonPath('pdf_boilerplate.quotation.intro', 'Berikut adalah sebut harga kami.');

        $this->company->refresh();
        $this->assertIsArray($this->company->pdf_boilerplate);
        $this->assertSame('Bayaran dalam 14 hari sahaja.', $this->company->pdf_boilerplate['invoice']['footer_terms']);
    }

    public function test_boilerplate_service_returns_default_when_company_override_missing(): void
    {
        $service = app(PdfBoilerplateService::class);
        $resolved = $service->resolve(null, 'invoice', 'Some Co');

        $this->assertSame('Yours faithfully,', $resolved['signature_left_intro']);
        $this->assertStringContainsString('Goods sold are not returnable', $resolved['footer_terms']);
        $this->assertStringContainsString('SOME CO', $resolved['footer_terms']);
    }
}
