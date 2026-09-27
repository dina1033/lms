<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Services\CalculateRefundAmount;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

it('refunds only unused months of an annual subscription', function () {
    $plan = Plan::factory()->annual()->create([
        'price_minor' => 12_000,
        'duration_months' => 12,
    ]);

    $subscription = Subscription::factory()->create([
        'plan_id' => $plan->id,
        'amount_minor' => 12_000,
        'starts_at' => '2026-01-10 10:00:00',
        'ends_at' => '2027-01-10 10:00:00',
    ]);

    $refund = app(CalculateRefundAmount::class)->execute(
        $subscription,
        Carbon\Carbon::parse('2026-05-15 12:00:00'),
    );

    expect($refund)->toBe(7_000);
});

it('treats the whole month as used when refund is requested at the beginning of the month', function () {
    $plan = Plan::factory()->annual()->create([
        'price_minor' => 12_000,
        'duration_months' => 12,
    ]);

    $subscription = Subscription::factory()->create([
        'plan_id' => $plan->id,
        'amount_minor' => 12_000,
        'starts_at' => '2026-01-10 10:00:00',
        'ends_at' => '2027-01-10 10:00:00',
    ]);

    $refund = app(CalculateRefundAmount::class)->execute(
        $subscription,
        Carbon\Carbon::parse('2026-05-01 00:01:00'),
    );

    expect($refund)->toBe(7_000);
});

it('treats the whole month as used when refund is requested at the end of the month', function () {
    $plan = Plan::factory()->annual()->create([
        'price_minor' => 12_000,
        'duration_months' => 12,
    ]);

    $subscription = Subscription::factory()->create([
        'plan_id' => $plan->id,
        'amount_minor' => 12_000,
        'starts_at' => '2026-01-10 10:00:00',
        'ends_at' => '2027-01-10 10:00:00',
    ]);

    $refund = app(CalculateRefundAmount::class)->execute(
        $subscription,
        Carbon\Carbon::parse('2026-05-31 23:59:59'),
    );

    expect($refund)->toBe(7_000);
});

it('refunds only the last unused month of a three-month subscription', function () {
    $plan = Plan::factory()->threeMonth()->create([
        'price_minor' => 3_000,
        'duration_months' => 3,
    ]);

    $subscription = Subscription::factory()->create([
        'plan_id' => $plan->id,
        'amount_minor' => 3_000,
        'starts_at' => '2026-01-10 10:00:00',
        'ends_at' => '2026-04-10 10:00:00',
    ]);

    $refund = app(CalculateRefundAmount::class)->execute(
        $subscription,
        Carbon\Carbon::parse('2026-02-20 12:00:00'),
    );

    expect($refund)->toBe(1_000);
});

it('does not refund a monthly subscription once its month has started', function () {
    $plan = Plan::factory()->monthly()->create([
        'price_minor' => 1_000,
        'duration_months' => 1,
    ]);

    $subscription = Subscription::factory()->create([
        'plan_id' => $plan->id,
        'amount_minor' => 1_000,
        'starts_at' => '2026-01-10 10:00:00',
        'ends_at' => '2026-02-10 10:00:00',
    ]);

    $refund = app(CalculateRefundAmount::class)->execute(
        $subscription,
        Carbon\Carbon::parse('2026-01-20 12:00:00'),
    );

    expect($refund)->toBe(0);
});

it('preserves every minor unit when refunding unused months', function () {
    $plan = Plan::factory()->threeMonth()->create([
        'price_minor' => 1_000,
        'duration_months' => 3,
    ]);

    $subscription = Subscription::factory()->create([
        'plan_id' => $plan->id,
        'amount_minor' => 1_000,
        'starts_at' => '2026-01-10 10:00:00',
        'ends_at' => '2026-04-10 10:00:00',
    ]);

    // Month 1 = 334
    // Month 2 = 333
    // Month 3 = 333
    //
    // Refund in month 1 => refund months 2 + 3 = 666.
    $refund = app(CalculateRefundAmount::class)->execute(
        $subscription,
        Carbon\Carbon::parse('2026-01-15 12:00:00'),
    );

    expect($refund)->toBe(666);
});