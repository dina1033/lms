<?php

namespace App\Providers;

use App\Contracts\PayoutProvider;
use App\Contracts\RefundProvider;
use App\Services\MockPayoutProvider;
use App\Services\MockRefundProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            PayoutProvider::class,
            MockPayoutProvider::class,
        );

        $this->app->bind(
            RefundProvider::class,
            MockRefundProvider::class,
        );
    }

    public function boot(): void
    {
        //
    }
}