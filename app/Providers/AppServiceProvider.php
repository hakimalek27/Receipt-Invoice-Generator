<?php

namespace App\Providers;

use App\Services\AmountInWordsService;
use App\Services\DocumentWorkflowService;
use App\Services\NumberingService;
use App\Services\DeepSeekParserService;
use App\Services\PdfRenderService;
use App\Services\TelegramConfirmationService;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NumberingService::class);
        $this->app->singleton(DocumentWorkflowService::class);
        $this->app->singleton(AmountInWordsService::class);
        $this->app->singleton(PdfRenderService::class);
        $this->app->singleton(DeepSeekParserService::class);
        $this->app->singleton(TelegramConfirmationService::class);
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
