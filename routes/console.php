<?php

use App\Models\Document;
use App\Models\User;
use App\Services\DeepSeekParserService;
use App\Services\PdfRenderService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('rig:pdf-smoke {--paper=A4}', function () {
    $document = Document::query()
        ->where('status', Document::STATUS_ISSUED)
        ->latest()
        ->first();

    if (! $document) {
        $this->error('No issued document found for PDF smoke.');

        return 1;
    }

    $render = app(PdfRenderService::class)
        ->render($document, (string) $this->option('paper'));

    $this->info("PDF smoke OK: {$render->file_path}");

    return 0;
})->purpose('Render the latest issued document through the configured PDF renderer.');

Artisan::command('rig:deepseek-smoke {prompt=invoice 1x Smoke item RM1} {--require-live}', function () {
    $user = User::query()
        ->whereNotNull('company_id')
        ->first();

    if (! $user) {
        $this->error('No company user found for DeepSeek smoke.');

        return 1;
    }

    $result = app(DeepSeekParserService::class)
        ->parseIntent((string) $this->argument('prompt'), $user->company_id);

    if ($this->option('require-live') && ($result['ai_status'] ?? null) !== 'deepseek') {
        $this->error('DeepSeek live-key smoke failed; parser used '.$result['ai_status']);

        return 1;
    }

    $this->info('DeepSeek smoke OK: '.json_encode([
        'ai_status' => $result['ai_status'] ?? null,
        'document_type' => $result['document_type'] ?? null,
        'item_count' => count($result['items'] ?? []),
    ]));

    return 0;
})->purpose('Run a redacted DeepSeek parser smoke; use --require-live in production.');
