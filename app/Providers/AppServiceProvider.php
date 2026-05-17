<?php

namespace App\Providers;

use App\Services\AmountInWordsService;
use App\Services\DocumentWorkflowService;
use App\Services\NumberingService;
use App\Services\PdfRenderService;
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
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
