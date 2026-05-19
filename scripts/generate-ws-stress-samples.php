<?php

/**
 * Generate stress-test sample PDFs for WS templates:
 * 1. Long invoice (22 items) - triggers multipage with compact header
 * 2. Quotation with 3 artwork attachments (design mockups)
 *
 * Run: php scripts/generate-ws-stress-samples.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Company;
use App\Models\Customer;
use App\Models\DocumentAttachment;
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
    ]);

    foreach (['invoice' => 'INV', 'quotation' => 'QUO'] as $type => $code) {
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

    $user = User::where('company_id', $company->id)->first()
        ?? User::factory()->create(['role' => 'admin', 'company_id' => $company->id]);

    $customer = Customer::firstOrCreate(
        ['company_id' => $company->id, 'name' => 'PIBG Sekolah Kebangsaan Wangsa Maju (Sample)'],
        [
            'address' => "Sekolah Kebangsaan Wangsa Maju Seksyen 5,\nJalan Wangsa Murni 1, Wangsa Maju,\n53300 Kuala Lumpur",
            'phone' => '03-4023 5678',
            'email' => 'pibg.skwm5@gmail.test',
            'is_active' => true,
        ]
    );

    $workflow = app(DocumentWorkflowService::class);
    $pdfService = app(PdfRenderService::class);
    $samplesDir = __DIR__ . '/../storage/app/samples';
    if (! is_dir($samplesDir)) {
        mkdir($samplesDir, 0755, true);
    }

    // ============ 1. Long invoice — 22 items, will overflow to page 2 ============
    $bigItems = [
        ['description' => 'Bunting 440gsm 1200dpi 2ft x 4ft', 'section_header' => 'EVENT BACKDROP & SIGNAGE', 'quantity' => 6, 'uom' => 'pcs', 'unit_price' => 10.00, 'discount' => 0],
        ['description' => 'Tripod Stand (Heavy Duty)', 'quantity' => 6, 'uom' => 'pcs', 'unit_price' => 15.00, 'discount' => 0],
        ['description' => "Framed Poster\n- Tarpaulin 380gsm\n- 12\"W x 18\"H\n- UV ink 1200 dpi", 'quantity' => 2, 'uom' => 'pcs', 'unit_price' => 75.00, 'discount' => 5.00],
        ['description' => 'Roll-up Banner 200x80cm (Premium PVC)', 'quantity' => 4, 'uom' => 'pcs', 'unit_price' => 120.00, 'discount' => 0],
        ['description' => 'Pull-up Banner Stand (Aluminium)', 'quantity' => 4, 'uom' => 'pcs', 'unit_price' => 85.00, 'discount' => 0],
        ['description' => 'Backdrop 8ft x 8ft (Full Color)', 'quantity' => 1, 'uom' => 'set', 'unit_price' => 320.00, 'discount' => 20.00],

        ['description' => 'Business Cards (Matte 350gsm, double-sided)', 'section_header' => 'PRINTED MATERIALS', 'quantity' => 500, 'uom' => 'pcs', 'unit_price' => 0.30, 'discount' => 0],
        ['description' => 'A5 Flyers (Glossy 150gsm)', 'quantity' => 1000, 'uom' => 'pcs', 'unit_price' => 0.20, 'discount' => 10.00],
        ['description' => 'A4 Brochure Bi-Fold (Art Paper 200gsm)', 'quantity' => 500, 'uom' => 'pcs', 'unit_price' => 0.65, 'discount' => 0],
        ['description' => 'A3 Poster (Photo Paper 240gsm)', 'quantity' => 50, 'uom' => 'pcs', 'unit_price' => 8.00, 'discount' => 0],
        ['description' => 'Sticker A6 (Vinyl, die-cut)', 'quantity' => 200, 'uom' => 'pcs', 'unit_price' => 1.50, 'discount' => 0],
        ['description' => 'Booklet A5 16-page Saddle Stitch', 'quantity' => 100, 'uom' => 'pcs', 'unit_price' => 4.50, 'discount' => 0],

        ['description' => "Polyester T-Shirt with Logo Print\n- Dry-fit material\n- Logo front + back\n- Sizes XS-XXXL", 'section_header' => 'UNIFORMS & MERCHANDISE', 'quantity' => 50, 'uom' => 'pcs', 'unit_price' => 25.00, 'discount' => 50.00],
        ['description' => 'Lanyard with Custom Print + Card Holder', 'quantity' => 100, 'uom' => 'pcs', 'unit_price' => 3.50, 'discount' => 0],
        ['description' => 'Tote Bag Canvas 14oz with Screen Print', 'quantity' => 100, 'uom' => 'pcs', 'unit_price' => 12.00, 'discount' => 0],
        ['description' => 'Pen with Logo (Plastic, Bulk)', 'quantity' => 200, 'uom' => 'pcs', 'unit_price' => 1.20, 'discount' => 0],
        ['description' => 'Cap Snapback with Embroidery', 'quantity' => 30, 'uom' => 'pcs', 'unit_price' => 18.00, 'discount' => 0],

        ['description' => 'Vehicle Decal Sticker (Cut-Vinyl Outdoor)', 'section_header' => 'VEHICLE & OUTDOOR', 'quantity' => 4, 'uom' => 'set', 'unit_price' => 45.00, 'discount' => 0],
        ['description' => 'Outdoor Banner 4ft x 12ft (Mesh Vinyl)', 'quantity' => 2, 'uom' => 'pcs', 'unit_price' => 180.00, 'discount' => 0],
        ['description' => 'PVC Foam Board Signage 4ft x 8ft', 'quantity' => 1, 'uom' => 'pcs', 'unit_price' => 240.00, 'discount' => 0],

        ['description' => 'Setup & Installation (Half-Day Crew)', 'section_header' => 'SERVICES', 'quantity' => 1, 'uom' => 'job', 'unit_price' => 200.00, 'discount' => 0],
        ['description' => 'Express Delivery (KL Klang Valley)', 'quantity' => 1, 'uom' => 'trip', 'unit_price' => 80.00, 'discount' => 0],
    ];

    $bigDraft = $workflow->createDraft([
        'company_id' => $company->id,
        'document_type' => 'invoice',
        'customer_id' => $customer->id,
        'document_date' => '2025-06-15',
        'due_date' => '2025-06-29',
        'currency' => 'MYR',
        'show_amount_in_words' => true,
        'amount_in_words_locale' => 'en_WEHDAH',
        'amount_in_words_currency' => 'MYR',
        'terms' => "Payment due within 14 days from invoice date.\n50% non-refundable deposit required for production go-ahead.",
        'items' => $bigItems,
    ]);
    $bigInvoice = $workflow->issue($bigDraft->id, $user->id);
    $bigRender = $pdfService->render($bigInvoice->fresh());
    $bigPath = $samplesDir . '/ws-invoice-large.pdf';
    file_put_contents($bigPath, Storage::disk('local')->get($bigRender->file_path));

    // ============ 2. Quotation with 3 artwork attachments ============
    $quoteItems = [
        ['description' => 'Logo Design (3 concepts + 2 revisions)', 'quantity' => 1, 'uom' => 'job', 'unit_price' => 850.00, 'discount' => 0],
        ['description' => 'Brand Guidelines Document (12 pages PDF)', 'quantity' => 1, 'uom' => 'doc', 'unit_price' => 450.00, 'discount' => 0],
        ['description' => "Stationery Set Design\n- Business card\n- Letterhead\n- Envelope\n- Email signature", 'quantity' => 1, 'uom' => 'set', 'unit_price' => 380.00, 'discount' => 30.00],
        ['description' => 'Social Media Templates (10 designs)', 'quantity' => 1, 'uom' => 'pack', 'unit_price' => 280.00, 'discount' => 0],
    ];

    $quoteDraft = $workflow->createDraft([
        'company_id' => $company->id,
        'document_type' => 'quotation',
        'customer_id' => $customer->id,
        'document_date' => '2025-06-15',
        'due_date' => '2025-06-29',
        'currency' => 'MYR',
        'show_amount_in_words' => true,
        'amount_in_words_locale' => 'en_WEHDAH',
        'amount_in_words_currency' => 'MYR',
        'terms' => "Quote valid for 14 days.\nDesign files delivered in editable Adobe Illustrator + PDF formats.\n50% deposit on confirmation, balance on final delivery.",
        'items' => $quoteItems,
    ]);
    $quote = $workflow->issue($quoteDraft->id, $user->id);

    // Generate 3 sample artwork PNGs (different colors) + attach
    $artworkLabels = [
        ['Logo Concept A — Primary',  [0x00, 0x20, 0x60], '#FFFFFF'],
        ['Logo Concept B — Alternate',[0xC0, 0xA0, 0x62], '#1F3A5F'],
        ['Brand Mood Board',          [0x1F, 0x3A, 0x5F], '#F4ECD8'],
    ];

    $attachmentDir = "documents/{$company->id}/{$quote->id}/attachments";

    foreach ($artworkLabels as $i => [$caption, $bg, $textColor]) {
        $w = 1240; $h = 1754; // A4 at 150dpi
        $im = imagecreatetruecolor($w, $h);
        $bgColor = imagecolorallocate($im, $bg[0], $bg[1], $bg[2]);
        [$r, $g, $b] = sscanf($textColor, '#%02x%02x%02x');
        $fg = imagecolorallocate($im, $r, $g, $b);
        $borderColor = imagecolorallocate($im, 0x1a, 0x1a, 0x1a);
        $accentColor = imagecolorallocate($im, 0xD4, 0xAF, 0x37);

        imagefilledrectangle($im, 0, 0, $w, $h, $bgColor);
        imagefilledrectangle($im, 0, 0, $w, 80, $accentColor);
        imagefilledrectangle($im, 0, $h - 80, $w, $h, $accentColor);

        // Header text
        $headerText = 'WEHDAH SOLUTION — Design Proof';
        imagestring($im, 5, 40, 30, $headerText, $fg);

        // Mockup title (multiple sizes for prominence)
        $titleX = 80;
        $titleY = (int) ($h / 2) - 200;
        imagettftext($im, 60, 0, $titleX, $titleY, $fg, '/usr/share/fonts/truetype/liberation/LiberationSerif-Bold.ttf', $caption);

        // Subline
        imagettftext($im, 24, 0, $titleX, $titleY + 80, $fg, '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf', 'Concept Iteration ' . ($i + 1) . ' of ' . count($artworkLabels));

        // Mock logo box
        $boxX1 = 200; $boxY1 = (int) ($h / 2) + 100;
        $boxX2 = $w - 200; $boxY2 = $h - 300;
        imagefilledrectangle($im, $boxX1, $boxY1, $boxX2, $boxY2, $fg);
        imagerectangle($im, $boxX1 - 4, $boxY1 - 4, $boxX2 + 4, $boxY2 + 4, $borderColor);

        // Inner logo text
        $logoText = 'WS';
        $logoSize = 200;
        $logoBbox = imagettfbbox($logoSize, 0, '/usr/share/fonts/truetype/liberation/LiberationSerif-Bold.ttf', $logoText);
        $logoW = $logoBbox[2] - $logoBbox[0];
        $logoH = $logoBbox[1] - $logoBbox[7];
        $logoX = (int) (($boxX1 + $boxX2 - $logoW) / 2);
        $logoY = (int) (($boxY1 + $boxY2 + $logoH) / 2);
        imagettftext($im, $logoSize, 0, $logoX, $logoY, $bgColor, '/usr/share/fonts/truetype/liberation/LiberationSerif-Bold.ttf', $logoText);

        // Footer
        imagettftext($im, 18, 0, 80, $h - 30, $fg, '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf', 'Approval required before production');

        $pngBytes = null;
        ob_start();
        imagepng($im, null, 6);
        $pngBytes = ob_get_clean();
        imagedestroy($im);

        $filename = sprintf('artwork-%02d.png', $i + 1);
        $storagePath = $attachmentDir . '/' . $filename;
        Storage::disk('local')->put($storagePath, $pngBytes);

        DocumentAttachment::create([
            'company_id' => $company->id,
            'document_id' => $quote->id,
            'original_name' => $filename,
            'storage_path' => $storagePath,
            'mime_type' => 'image/png',
            'size_bytes' => strlen($pngBytes),
            'caption' => $caption,
            'sort_order' => $i + 1,
            'include_in_pdf' => true,
        ]);
    }

    $quote->load('attachments');
    $quoteRender = $pdfService->render($quote->fresh());
    $quotePath = $samplesDir . '/ws-quotation-with-artwork.pdf';
    file_put_contents($quotePath, Storage::disk('local')->get($quoteRender->file_path));

    $bigSize = filesize($bigPath);
    $quoteSize = filesize($quotePath);

    echo "Generated stress samples:\n";
    echo sprintf("  %-30s %s  (%s bytes)  %s\n", 'Long invoice (22 items):', $bigInvoice->official_number, number_format($bigSize), $bigPath);
    echo sprintf("  %-30s %s  (%s bytes)  %s\n", 'Quote + 3 artworks:', $quote->official_number, number_format($quoteSize), $quotePath);

    DB::rollBack();
    echo "\nDB rolled back. Sample PDFs persisted to disk.\n";
} catch (\Throwable $e) {
    DB::rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
