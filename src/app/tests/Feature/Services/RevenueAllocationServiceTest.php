<?php

use App\Models\Instructor;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\SubscriptionInstructor;
use App\Services\RevenueAllocationService;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);


beforeEach(function () {
    config()->set('services.platform.revenue_percentage', 20);
});

it('allocates the revenue pool equally among involved instructors', function () {
    $user = User::factory()->create();

    $plan = Plan::factory()->create([
        'price_minor' => 10_000,
        'currency' => 'USD',
    ]);

    $subscription = Subscription::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'amount_minor' => 10_000,
        'currency' => 'USD',
    ]);

    $instructorA = Instructor::factory()->create();
    $instructorB = Instructor::factory()->create();
    $instructorC = Instructor::factory()->create();

    $subscription->instructors()->attach([
        $instructorA->id,
        $instructorB->id,
        $instructorC->id,
    ]);

    app(RevenueAllocationService::class)->allocate($subscription);

    $allocations = $subscription->revenueAllocations()
        ->orderBy('instructor_id')
        ->get();

    expect($allocations)->toHaveCount(3);

    expect($allocations->pluck('amount_minor')->all())
        ->toBe([2667, 2667, 2666]);

    expect($allocations->sum('amount_minor'))
        ->toBe(8_000);
});

it('deducts the platform percentage before instructor allocation', function () {
    $user = User::factory()->create();

    $plan = Plan::factory()->create([
        'price_minor' => 10_000,
        'currency' => 'USD',
    ]);

    $subscription = Subscription::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'amount_minor' => 10_000,
        'currency' => 'USD',
    ]);

    $instructor = Instructor::factory()->create();

    $subscription->instructors()->attach($instructor);

    app(RevenueAllocationService::class)->allocate($subscription);

    $allocation = $subscription->revenueAllocations()->first();

    expect($allocation->amount_minor)
        ->toBe(8_000);

    expect((float) $allocation->percentage)
        ->toBe(100.0);
});

it('does not create allocations when the subscription has no instructors', function () {
    $user = User::factory()->create();

    $plan = Plan::factory()->create([
        'price_minor' => 10_000,
        'currency' => 'USD',
    ]);

    $subscription = Subscription::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'amount_minor' => 10_000,
        'currency' => 'USD',
    ]);

    app(RevenueAllocationService::class)->allocate($subscription);

    expect($subscription->revenueAllocations()->count())
        ->toBe(0);
});

it('preserves every minor unit when splitting the instructor pool', function () {
    $user = User::factory()->create();

    $plan = Plan::factory()->create([
        'price_minor' => 999,
        'currency' => 'USD',
    ]);

    $subscription = Subscription::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'amount_minor' => 999,
        'currency' => 'USD',
    ]);

    $instructors = Instructor::factory()->count(4)->create();

    $subscription->instructors()->attach(
        $instructors->pluck('id')
    );

    app(RevenueAllocationService::class)->allocate($subscription);

    $allocations = $subscription->revenueAllocations()->get();

    expect($allocations->sum('amount_minor'))
        ->toBe(799);

    expect($allocations->sum(fn ($allocation) => (float) $allocation->percentage))
        ->toBe(100.0);
});

it('does not create duplicate allocations when called twice', function () {
    $user = User::factory()->create();

    $plan = Plan::factory()->create([
        'price_minor' => 10_000,
        'currency' => 'USD',
    ]);

    $subscription = Subscription::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'amount_minor' => 10_000,
        'currency' => 'USD',
    ]);

    $instructors = Instructor::factory()->count(3)->create();

    $subscription->instructors()->attach(
        $instructors->pluck('id')
    );

    $service = app(RevenueAllocationService::class);

    $service->allocate($subscription);
    $service->allocate($subscription);

    expect($subscription->revenueAllocations()->count())
        ->toBe(3);

    expect((int) $subscription->revenueAllocations()->sum('amount_minor'))
        ->toBe(8_000);
});