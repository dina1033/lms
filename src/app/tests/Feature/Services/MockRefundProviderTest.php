<?php

use App\Contracts\RefundProviderResult;
use App\Models\Refund;
use App\Services\MockRefundProvider;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

it('returns a successful refund provider result', function () {
    $refund = Refund::factory()->create();

    $provider = new MockRefundProvider();

    $result = $provider->refund($refund);

    expect($result)
        ->toBeInstanceOf(RefundProviderResult::class)
        ->and($result->status)->toBe('succeeded')
        ->and($result->providerReference)
        ->toBe('mock-refund-' . $refund->id)
        ->and($provider->calls)->toBe(1);
});

it('returns a successful status when checking refund status', function () {
    $refund = Refund::factory()->create([
        'provider_reference' => null,
    ]);

    $provider = new MockRefundProvider();

    $result = $provider->checkStatus($refund);

    expect($result)
        ->toBeInstanceOf(RefundProviderResult::class)
        ->and($result->status)->toBe('succeeded')
        ->and($result->providerReference)
        ->toBe('mock-refund-' . $refund->id);
});