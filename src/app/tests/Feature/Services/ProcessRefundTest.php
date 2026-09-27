<?php

use App\Enums\RefundStatus;
use App\Models\Refund;
use App\Services\MockRefundProvider;
use App\Services\ProcessRefund;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

it('marks the refund as succeeded when the provider succeeds', function () {
    $refund = Refund::factory()->create([
        'status' => RefundStatus::PENDING,
    ]);

    $provider = new MockRefundProvider();

    $service = new ProcessRefund($provider);

    $result = $service->execute($refund);

    expect($result->status)
        ->toBe(RefundStatus::SUCCEEDED)
        ->and($result->provider_reference)
        ->toBe('mock-refund-' . $refund->id)
        ->and($result->refunded_at)
        ->not->toBeNull()
        ->and($provider->calls)
        ->toBe(1);
});

it('marks the refund as failed when the provider fails', function () {
    $refund = Refund::factory()->create([
        'status' => RefundStatus::PENDING,
    ]);

    $provider = new MockRefundProvider(
        refundStatus: 'failed',
        failureReason: 'Insufficient funds',
    );

    $service = new ProcessRefund($provider);

    $result = $service->execute($refund);

    expect($result->status)
        ->toBe(RefundStatus::FAILED)
        ->and($result->provider_reference)
        ->toBeNull()
        ->and($result->failure_reason)
        ->toBe('Insufficient funds')
        ->and($result->refunded_at)
        ->toBeNull()
        ->and($provider->calls)
        ->toBe(1);
});

it('marks the refund as unknown when the provider times out', function () {
    $refund = Refund::factory()->create([
        'status' => RefundStatus::PENDING,
    ]);

    $provider = new MockRefundProvider();

    $provider->shouldTimeout = true;

    $service = new ProcessRefund($provider);

    $result = $service->execute($refund);

    expect($result->status)
        ->toBe(RefundStatus::UNKNOWN)
        ->and($result->failure_reason)
        ->toBe('Provider timeout')
        ->and($result->refunded_at)
        ->toBeNull()
        ->and($provider->calls)
        ->toBe(1);
});

it('reconciles an unknown refund as succeeded', function () {
    $refund = Refund::factory()->create([
        'status' => RefundStatus::UNKNOWN,
        'provider_reference' => null,
    ]);

    $provider = new MockRefundProvider(
        refundStatus: 'succeeded',
    );

    $service = new ProcessRefund($provider);

    $result = $service->reconcile($refund);

    expect($result->status)
        ->toBe(RefundStatus::SUCCEEDED)
        ->and($result->provider_reference)
        ->toBe('mock-refund-' . $refund->id)
        ->and($result->refunded_at)
        ->not->toBeNull()
        ->and($provider->calls)
        ->toBe(0);
});

it('reconciles an unknown refund as failed', function () {
    $refund = Refund::factory()->create([
        'status' => RefundStatus::UNKNOWN,
    ]);

    $provider = new MockRefundProvider(
        refundStatus: 'failed',
        failureReason: 'Refund rejected',
    );

    $service = new ProcessRefund($provider);

    $result = $service->reconcile($refund);

    expect($result->status)
        ->toBe(RefundStatus::FAILED)
        ->and($result->failure_reason)
        ->toBe('Refund rejected')
        ->and($result->refunded_at)
        ->toBeNull()
        ->and($provider->calls)
        ->toBe(0);
});