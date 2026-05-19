<?php

/**
 * Reproduce the exact scenario from production document4.pdf:
 *  - Walk-in customer
 *  - Empty items
 *  - 1 artwork attachment (portrait photo)
 * Generated locally on the feature branch to verify the redesigned WS template
 * produces output matching the samples (not the old generic Malay template).
 *
 * Run: php scripts/generate-ws-repro-document4.php
 * Output: storage/app/samples/ws-repro-document4.pdf
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Company;
use App\Models\CompanyBankAccount;
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

    if (! CompanyBankAccount::where('company_id', $company->id)->exists()) {
        CompanyBankAccount::create([
            'company_id' => $company->id, 'bank_name' => 'Hong Leong Islamic',
            'account_number' => '18701038380', 'is_primary' => true, 'sort_order' => 1,
        ]);
        CompanyBankAccount::create([
            'company_id' => $company->id, 'bank_name' => 'Bank Islam',
            'account_number' => '12113010769313', 'sort_order' => 2,
        ]);
    }

    NumberingPolicy::firstOrCreate(
        ['company_id' => $company->id, 'document_type' => 'invoice'],
        ['prefix' => 'WS-INV', 'separator' => '-', 'year_token' => '{YYYY}', 'sequence_padding' => 5, 'reset_policy' => 'yearly', 'is_active' => true]
    );

    $user = User::where('company_id', $company->id)->first()
        ?? User::factory()->create(['role' => 'admin', 'company_id' => $company->id]);

    $workflow = app(DocumentWorkflowService::class);
    $pdfService = app(PdfRenderService::class);

    $draft = $workflow->createDraft([
        'company_id' => $company->id,
        'document_type' => 'invoice',
        'document_date' => now()->toDateString(),
        'currency' => 'MYR',
        'items' => [],
    ]);

    // Attach a sample portrait JPG (use any tracking pixel for the placeholder).
    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAYAAACqaXHeAAAATklEQVR42u3PMQEAAAjAINc/9CXhBwsg0AKzZi0EBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBARGCw57DEAjJg7dAAAAAElFTkSuQmCC');
    $attachPath = "documents/{$company->id}/{$draft->id}/attachments/portrait.png";
    Storage::disk('local')->put($attachPath, $pngBytes);
    DocumentAttachment::create([
        'company_id' => $company->id,
        'document_id' => $draft->id,
        'original_name' => 'Untitled design (1).jpg',
        'storage_path' => $attachPath,
        'mime_type' => 'image/png',
        'size_bytes' => strlen($pngBytes),
        'caption' => 'Portrait',
        'sort_order' => 1,
        'include_in_pdf' => true,
    ]);

    $draft->load('attachments');
    $render = $pdfService->render($draft->fresh());

    $samplesDir = __DIR__ . '/../storage/app/samples';
    if (! is_dir($samplesDir)) {
        mkdir($samplesDir, 0755, true);
    }
    $outPath = $samplesDir . '/ws-repro-document4.pdf';
    file_put_contents($outPath, Storage::disk('local')->get($render->file_path));

    echo "Local feature-branch render: {$outPath}\n";
    echo "Bytes: ".filesize($outPath).", pages: {$render->page_count}\n";
    echo "Template used: {$render->template_used}\n";

    DB::rollBack();
    echo "DB rolled back.\n";
} catch (\Throwable $e) {
    DB::rollBack();
    echo "ERROR: ".$e->getMessage()."\n";
    exit(1);
}
