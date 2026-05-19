<?php

/**
 * Generate a full Wehdah Solution sample PDF set (invoice, quotation, delivery_order, official_receipt)
 * using the redesigned WS templates.
 *
 * Run: php scripts/generate-ws-samples.php
 * Output: storage/app/samples/ws-{type}.pdf
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Company;
use App\Models\CompanyBankAccount;
use App\Models\Customer;
use App\Models\NumberingPolicy;
use App\Models\Payment;
use App\Models\User;
use App\Services\DocumentWorkflowService;
use App\Services\PdfRenderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

DB::beginTransaction();

try {
    $company = Company::where('code', 'WS')->first();
    if (! $company) {
        $company = Company::factory()->wehdah()->create();
    }
    $company->update([
        'brand_primary' => '#002060',
        'brand_secondary' => '#f4f7fa',
        'brand_accent' => '#1F3A5F',
    ]);

    if (! CompanyBankAccount::where('company_id', $company->id)->exists()) {
        CompanyBankAccount::create([
            'company_id' => $company->id,
            'bank_name' => 'Hong Leong Islamic',
            'account_number' => '18701038380',
            'is_primary' => true,
            'sort_order' => 1,
        ]);
        CompanyBankAccount::create([
            'company_id' => $company->id,
            'bank_name' => 'Bank Islam',
            'account_number' => '12113010769313',
            'sort_order' => 2,
        ]);
    }

    foreach (['invoice' => 'INV', 'quotation' => 'QUO', 'delivery_order' => 'DO', 'official_receipt' => 'OR'] as $type => $code) {
        NumberingPolicy::firstOrCreate(
            ['company_id' => $company->id, 'document_type' => $type],
            [
                'prefix' => 'WS-' . $code,
                'separator' => '-',
                'year_token' => '{YYYY}',
                'sequence_padding' => 5,
                'reset_policy' => 'yearly',
                'is_active' => true,
            ]
        );
    }

    $user = User::where('company_id', $company->id)->first();
    if (! $user) {
        $user = User::factory()->create([
            'role' => 'admin',
            'company_id' => $company->id,
        ]);
    }

    $customer = Customer::firstOrCreate(
        ['company_id' => $company->id, 'name' => 'Muhammad Hakim (Sample)'],
        [
            'address' => "Masjid Al-Muttaqin Wangsa Melawati,\nJalan Wangsa Melawati 6,\n53300 Kuala Lumpur",
            'phone' => '018-9030363',
            'email' => 'hakim@wangsa-masjid.test',
            'is_active' => true,
        ]
    );

    $workflow = app(DocumentWorkflowService::class);
    $pdfService = app(PdfRenderService::class);

    $items = [
        [
            'description' => 'Bunting 440gsm 1200dpi 2ft x 4ft',
            'quantity' => 6,
            'uom' => 'pcs',
            'unit_price' => 10.00,
            'discount' => 0,
        ],
        [
            'description' => 'Tripod Stand',
            'quantity' => 6,
            'uom' => 'pcs',
            'unit_price' => 15.00,
            'discount' => 0,
        ],
        [
            'description' => "Framed Poster\n- Tarpaulin 380gsm\n- 12\"W x 18\"H\n- UV ink 1200 dpi\n- Wood frame",
            'section_header' => 'Display Items',
            'quantity' => 2,
            'uom' => 'pcs',
            'unit_price' => 75.00,
            'discount' => 5.00,
        ],
    ];

    $samplesDir = __DIR__ . '/../storage/app/samples';
    if (! is_dir($samplesDir)) {
        mkdir($samplesDir, 0755, true);
    }

    $outputs = [];

    // --- Invoice ---
    $invoiceDraft = $workflow->createDraft([
        'company_id' => $company->id,
        'document_type' => 'invoice',
        'customer_id' => $customer->id,
        'document_date' => '2025-06-10',
        'due_date' => '2025-06-10',
        'currency' => 'MYR',
        'show_amount_in_words' => true,
        'amount_in_words_locale' => 'en_WEHDAH',
        'amount_in_words_currency' => 'MYR',
        'items' => $items,
    ]);
    $invoice = $workflow->issue($invoiceDraft->id, $user->id);
    $invoiceRender = $pdfService->render($invoice->fresh());
    $invoicePath = $samplesDir . '/ws-invoice.pdf';
    file_put_contents($invoicePath, Storage::disk('local')->get($invoiceRender->file_path));
    $outputs['Invoice'] = ['number' => $invoice->official_number, 'path' => $invoicePath];

    // --- Quotation ---
    $quoteDraft = $workflow->createDraft([
        'company_id' => $company->id,
        'document_type' => 'quotation',
        'customer_id' => $customer->id,
        'document_date' => '2025-06-10',
        'due_date' => '2025-06-24',
        'currency' => 'MYR',
        'show_amount_in_words' => true,
        'amount_in_words_locale' => 'en_WEHDAH',
        'amount_in_words_currency' => 'MYR',
        'terms' => "Valid for 14 days from quotation date.\nPrices exclude any applicable taxes.\nPayment terms: 50% deposit upon confirmation, balance on delivery.",
        'items' => $items,
    ]);
    $quote = $workflow->issue($quoteDraft->id, $user->id);
    $quoteRender = $pdfService->render($quote->fresh());
    $quotePath = $samplesDir . '/ws-quotation.pdf';
    file_put_contents($quotePath, Storage::disk('local')->get($quoteRender->file_path));
    $outputs['Quotation'] = ['number' => $quote->official_number, 'path' => $quotePath];

    // --- Delivery Order ---
    $doDraft = $workflow->createDraft([
        'company_id' => $company->id,
        'document_type' => 'delivery_order',
        'customer_id' => $customer->id,
        'document_date' => '2025-06-12',
        'currency' => 'MYR',
        'notes' => 'Please verify all items upon receipt.',
        'items' => $items,
    ]);
    $do = $workflow->issue($doDraft->id, $user->id);
    $doRender = $pdfService->render($do->fresh());
    $doPath = $samplesDir . '/ws-delivery-order.pdf';
    file_put_contents($doPath, Storage::disk('local')->get($doRender->file_path));
    $outputs['Delivery Order'] = ['number' => $do->official_number, 'path' => $doPath];

    // --- Official Receipt (payment-driven) ---
    $payment = $workflow->recordPayment([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'amount' => $invoice->grand_total,
        'currency' => 'MYR',
        'method' => 'bank_transfer',
        'reference_number' => 'BIMB-2025-06-12-001',
        'payment_date' => '2025-06-12',
        'allocations' => [['document_id' => $invoice->id, 'amount' => $invoice->grand_total]],
        'create_official_receipt' => true,
    ]);

    $receipt = $payment->receiptDocument()->first();
    $receiptRender = $pdfService->render($receipt->fresh());
    $receiptPath = $samplesDir . '/ws-official-receipt.pdf';
    file_put_contents($receiptPath, Storage::disk('local')->get($receiptRender->file_path));
    $outputs['Official Receipt'] = ['number' => $receipt->official_number, 'path' => $receiptPath];

    echo "Generated 4 PDF samples:\n";
    foreach ($outputs as $label => $info) {
        $size = filesize($info['path']);
        echo sprintf("  %-20s %s  (%s bytes)  %s\n", $label . ':', $info['number'], number_format($size), $info['path']);
    }

    DB::rollBack();
    echo "\nDB rolled back. Sample PDFs persisted to disk.\n";
} catch (\Throwable $e) {
    DB::rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
