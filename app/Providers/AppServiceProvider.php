<?php

namespace App\Providers;

use App\Services\NumberingService;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NumberingService::class);
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
