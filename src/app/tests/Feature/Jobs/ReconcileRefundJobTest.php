<?php

use App\Enums\RefundStatus;
use App\Jobs\ReconcileRefundJob;
use App\Models\Refund;
use App\Services\MockRefundProvider;
use App\Services\ProcessRefund;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

it('reconciles an unknown refund through the refund service', function () {
    $refund = Refund::factory()->create([
        'status' => RefundStatus::UNKNOWN,
    ]);

    $provider = new MockRefundProvider(
        refundStatus: 'succeeded',
    );

    $this->app->instance(
        \App\Contracts\RefundProvider::class,
        $provider
    );

    $job = new ReconcileRefundJob($refund->id);

    $job->handle(
        $this->app->make(ProcessRefund::class)
    );

    expect($refund->refresh()->status)
        ->toBe(RefundStatus::SUCCEEDED);
});