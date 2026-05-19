<?php

/**
 * Demo: how a company can override the PDF boilerplate (intro, footer terms,
 * signature labels) without changing any code.
 *
 * Run: php scripts/generate-ws-custom-boilerplate-sample.php
 * Outputs storage/app/samples/ws-invoice-custom-text.pdf
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Company;
use App\Models\Customer;
use App\Models\NumberingPolicy;
use App\Models\User;
use App\Services\DocumentWorkflowService;
use App\Services\PdfRenderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

DB::beginTransaction();

try {
    $company = Company::where('code', 'WS')->first() ?? Company::factory()->wehdah()->create();
    $company->update([
        'brand_primary' => '#002060',
        'brand_secondary' => '#f4f7fa',
        'brand_accent' => '#1F3A5F',
        'pdf_boilerplate' => [
            'invoice' => [
                'footer_terms' => "Bayaran perlu dijelaskan dalam tempoh 14 hari dari tarikh invois.\nSemua cek hendaklah dipalang dan dibayar kepada {company_name}.\nDeposit 50% diperlukan sebelum kerja dimulakan.",
                'signature_left_intro' => 'Yang ikhlas,',
                'signature_left_label' => 'Pengurus Akaun',
                'signature_right_intro' => 'Barang diterima dengan sempurna,',
                'signature_right_label' => 'Cop & Tandatangan Pelanggan',
            ],
        ],
    ]);

    NumberingPolicy::firstOrCreate(
        ['company_id' => $company->id, 'document_type' => 'invoice'],
        ['prefix' => 'WS-INV', 'separator' => '-', 'year_token' => '{YYYY}', 'sequence_padding' => 5, 'reset_policy' => 'yearly', 'is_active' => true]
    );

    $user = User::where('company_id', $company->id)->first()
        ?? User::factory()->create(['role' => 'admin', 'company_id' => $company->id]);

    $customer = Customer::firstOrCreate(
        ['company_id' => $company->id, 'name' => 'PIBG Sekolah Sample'],
        ['address' => "Jalan Wangsa 6,\n53300 KL", 'phone' => '03-9876543', 'email' => 'pibg@test.local', 'is_active' => true]
    );

    $workflow = app(DocumentWorkflowService::class);
    $pdfService = app(PdfRenderService::class);

    $draft = $workflow->createDraft([
        'company_id' => $company->id,
        'document_type' => 'invoice',
        'customer_id' => $customer->id,
        'document_date' => '2025-06-10',
        'due_date' => '2025-06-24',
        'currency' => 'MYR',
        'show_amount_in_words' => true,
        'amount_in_words_locale' => 'en_WEHDAH',
        'amount_in_words_currency' => 'MYR',
        'items' => [
            ['description' => 'Bunting 440gsm 1200dpi 2ft x 4ft', 'quantity' => 6, 'uom' => 'pcs', 'unit_price' => 10.00],
            ['description' => 'Tripod Stand', 'quantity' => 6, 'uom' => 'pcs', 'unit_price' => 15.00],
        ],
    ]);
    $invoice = $workflow->issue($draft->id, $user->id);
    $render = $pdfService->render($invoice->fresh());

    $samplesDir = __DIR__ . '/../storage/app/samples';
    if (! is_dir($samplesDir)) {
        mkdir($samplesDir, 0755, true);
    }
    $outPath = $samplesDir . '/ws-invoice-custom-text.pdf';
    file_put_contents($outPath, Storage::disk('local')->get($render->file_path));

    echo "Custom-boilerplate invoice -> {$invoice->official_number}\n";
    echo "Output: {$outPath}\n";

    DB::rollBack();
    echo "DB rolled back.\n";
} catch (\Throwable $e) {
    DB::rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
