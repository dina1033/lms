<?php

use App\Enums\RefundStatus;
use App\Jobs\ProcessRefundJob;
use App\Models\Refund;
use App\Services\MockRefundProvider;
use App\Services\ProcessRefund;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

it('processes a refund through the refund service', function () {
    $refund = Refund::factory()->create([
        'status' => RefundStatus::PENDING,
    ]);

    $provider = new MockRefundProvider();

    $this->app->instance(
        \App\Contracts\RefundProvider::class,
        $provider
    );

    $job = new ProcessRefundJob($refund->id);

    $job->handle(
        $this->app->make(ProcessRefund::class)
    );

    expect($refund->refresh()->status)
        ->toBe(RefundStatus::SUCCEEDED)
        ->and($provider->calls)
        ->toBe(1);
});