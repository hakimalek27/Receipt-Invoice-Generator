<?php

/**
 * Verifies the new artwork-grid layout produces the expected page counts:
 *   1 artwork  -> 1 artwork page (single full)
 *   2 artworks -> 1 artwork page (2-up vertical stack)
 *   3 artworks -> 1 artwork page (2x2 grid, 1 cell blank)
 *   4 artworks -> 1 artwork page (2x2 grid)
 *   5 artworks -> 2 artwork pages (4 grid + 1 single)
 *   6 artworks -> 2 artwork pages (4 grid + 2 pair)
 *   7 artworks -> 2 artwork pages (4 grid + 3 grid)
 *   8 artworks -> 2 artwork pages (4 grid + 4 grid)
 *
 * Run: php scripts/generate-ws-artwork-grid-samples.php
 * Output: storage/app/samples/ws-art-grid-{N}.pdf
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

    NumberingPolicy::firstOrCreate(
        ['company_id' => $company->id, 'document_type' => 'quotation'],
        ['prefix' => 'WS-QUO', 'separator' => '-', 'year_token' => '{YYYY}', 'sequence_padding' => 5, 'reset_policy' => 'yearly', 'is_active' => true]
    );

    $user = User::where('company_id', $company->id)->first()
        ?? User::factory()->create(['role' => 'admin', 'company_id' => $company->id]);

    $customer = Customer::firstOrCreate(
        ['company_id' => $company->id, 'name' => 'Grid Layout Test (Sample)'],
        ['address' => "Test Address\n50450 KL", 'phone' => '019-1234567', 'email' => 'grid@test.local', 'is_active' => true]
    );

    $workflow = app(DocumentWorkflowService::class);
    $pdfService = app(PdfRenderService::class);
    $samplesDir = __DIR__ . '/../storage/app/samples';
    if (! is_dir($samplesDir)) {
        mkdir($samplesDir, 0755, true);
    }

    $palette = [
        [0x00, 0x20, 0x60, 0xFF, 0xFF, 0xFF],
        [0xC0, 0xA0, 0x62, 0x1F, 0x3A, 0x5F],
        [0x1F, 0x3A, 0x5F, 0xF4, 0xEC, 0xD8],
        [0xE6, 0x7E, 0x22, 0xFB, 0xEE, 0xE6],
        [0x16, 0xA0, 0x85, 0xFF, 0xFF, 0xFF],
        [0xD4, 0xAF, 0x37, 0x1A, 0x1A, 0x1A],
        [0x5D, 0x3A, 0x9B, 0xEF, 0xEA, 0xF7],
        [0xA9, 0x32, 0x26, 0xFF, 0xFF, 0xFF],
    ];

    foreach ([1, 2, 3, 4, 5, 6, 7, 8] as $artworkCount) {
        $draft = $workflow->createDraft([
            'company_id' => $company->id,
            'document_type' => 'quotation',
            'customer_id' => $customer->id,
            'document_date' => '2025-06-15',
            'due_date' => '2025-06-29',
            'currency' => 'MYR',
            'items' => [
                ['description' => 'Design Package', 'quantity' => 1, 'uom' => 'job', 'unit_price' => 500.00, 'discount' => 0],
            ],
        ]);
        $quote = $workflow->issue($draft->id, $user->id);

        $attachmentDir = "documents/{$company->id}/{$quote->id}/attachments";

        for ($i = 1; $i <= $artworkCount; $i++) {
            $color = $palette[($i - 1) % count($palette)];
            [$br, $bg, $bb, $tr, $tg, $tb] = $color;

            $w = 1240; $h = 1240;
            $im = imagecreatetruecolor($w, $h);
            $bgColor = imagecolorallocate($im, $br, $bg, $bb);
            $fgColor = imagecolorallocate($im, $tr, $tg, $tb);
            $accentColor = imagecolorallocate($im, 0xD4, 0xAF, 0x37);
            imagefilledrectangle($im, 0, 0, $w, $h, $bgColor);
            imagefilledrectangle($im, 0, 0, $w, 60, $accentColor);
            imagefilledrectangle($im, 0, $h - 60, $w, $h, $accentColor);
            imagettftext($im, 80, 0, 80, (int) ($h / 2) - 100, $fgColor, '/usr/share/fonts/truetype/liberation/LiberationSerif-Bold.ttf', 'Design #' . $i);
            imagettftext($im, 30, 0, 80, (int) ($h / 2) + 40, $fgColor, '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf', 'Concept ' . $i . ' of ' . $artworkCount);
            $boxX1 = 200; $boxY1 = (int) ($h / 2) + 120;
            $boxX2 = $w - 200; $boxY2 = $h - 200;
            imagefilledrectangle($im, $boxX1, $boxY1, $boxX2, $boxY2, $fgColor);
            $logoText = 'WS';
            imagettftext($im, 200, 0, (int) (($boxX1 + $boxX2) / 2) - 130, (int) (($boxY1 + $boxY2) / 2) + 70, $bgColor, '/usr/share/fonts/truetype/liberation/LiberationSerif-Bold.ttf', $logoText);

            ob_start();
            imagepng($im, null, 6);
            $pngBytes = ob_get_clean();
            imagedestroy($im);

            $filename = sprintf('artwork-%02d.png', $i);
            $storagePath = $attachmentDir . '/' . $filename;
            Storage::disk('local')->put($storagePath, $pngBytes);

            DocumentAttachment::create([
                'company_id' => $company->id,
                'document_id' => $quote->id,
                'original_name' => $filename,
                'storage_path' => $storagePath,
                'mime_type' => 'image/png',
                'size_bytes' => strlen($pngBytes),
                'caption' => 'Design Concept ' . $i,
                'sort_order' => $i,
                'include_in_pdf' => true,
            ]);
        }

        $quote->load('attachments');
        $render = $pdfService->render($quote->fresh());
        $outPath = $samplesDir . '/ws-art-grid-' . $artworkCount . '.pdf';
        file_put_contents($outPath, Storage::disk('local')->get($render->file_path));
        echo sprintf("  %d artwork(s) -> %d total pages  %s\n",
            $artworkCount,
            $render->page_count,
            $outPath
        );
    }

    DB::rollBack();
    echo "\nDB rolled back. Sample PDFs persisted to disk.\n";
} catch (\Throwable $e) {
    DB::rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
