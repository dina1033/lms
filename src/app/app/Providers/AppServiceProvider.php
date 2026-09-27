<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\PayoutProvider;
use App\Services\MockPayoutProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            PayoutProvider::class,
            MockPayoutProvider::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
