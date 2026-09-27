<?php

use App\Enums\LedgerEntryType;
use App\Models\Instructor;
use App\Models\Plan;
use App\Models\RevenueAllocation;
use App\Models\Subscription;
use App\Services\CreateInstructorLedgerEntries;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);
it('creates one ledger entry for each revenue allocation for the given month', function () {
    $plan = Plan::factory()->create([
        'duration_months' => 3,
    ]);
    $subscription = Subscription::factory()->create([
        'plan_id' => $plan->id,
        'starts_at' => Carbon::parse('2026-01-15'),
        'ends_at' => Carbon::parse('2026-04-15'),
    ]);

    $instructor1 = Instructor::factory()->create();
    $instructor2 = Instructor::factory()->create();

    RevenueAllocation::factory()->create([
        'subscription_id' => $subscription->id,
        'instructor_id' => $instructor1->id,
        'amount_minor' => 1000,
        'currency' => 'USD',
    ]);

    RevenueAllocation::factory()->create([
        'subscription_id' => $subscription->id,
        'instructor_id' => $instructor2->id,
        'amount_minor' => 2000,
        'currency' => 'USD',
    ]);

    app(CreateInstructorLedgerEntries::class)->execute(
        $subscription,
        Carbon::parse('2026-02-01'),
    );

    $entries = $subscription->ledgerEntries()
        ->orderBy('instructor_id')
        ->get();

    expect($entries)->toHaveCount(2);

    expect($entries->pluck('amount_minor')->all())
        ->toBe([333, 667]);

    expect($entries->every(
        fn ($entry) => $entry->type === LedgerEntryType::REVENUE
    ))->toBeTrue();

    expect($entries->every(
        fn ($entry) => $entry->occurred_at->isSameDay(
            Carbon::parse('2026-02-28')
        )
    ))->toBeTrue();
});

it('distributes remainder deterministically across months', function () {
    $plan = Plan::factory()->create([
        'duration_months' => 3,
    ]);

    $subscription = Subscription::factory()->create([
        'plan_id' => $plan->id,
        'starts_at' => Carbon::parse('2026-01-15'),
        'ends_at' => Carbon::parse('2026-04-15'),
    ]);

    $instructor = Instructor::factory()->create();

    RevenueAllocation::factory()->create([
        'subscription_id' => $subscription->id,
        'instructor_id' => $instructor->id,
        'amount_minor' => 1000,
        'currency' => 'USD',
    ]);

    $service = app(CreateInstructorLedgerEntries::class);

    $service->execute($subscription, Carbon::parse('2026-01-01'));
    $service->execute($subscription, Carbon::parse('2026-02-01'));
    $service->execute($subscription, Carbon::parse('2026-03-01'));

    $amounts = $subscription->ledgerEntries()
        ->orderBy('occurred_at')
        ->pluck('amount_minor')
        ->all();

    expect($amounts)->toBe([334, 333, 333]);
});

it('does not create duplicate ledger entries for the same month', function () {
    $plan = Plan::factory()->create([
        'duration_months' => 3,
    ]);

    $subscription = Subscription::factory()->create([
        'plan_id' => $plan->id,
        'starts_at' => Carbon::parse('2026-01-15'),
        'ends_at' => Carbon::parse('2026-04-15'),
    ]);

    $instructor = Instructor::factory()->create();

    RevenueAllocation::factory()->create([
        'subscription_id' => $subscription->id,
        'instructor_id' => $instructor->id,
        'amount_minor' => 1000,
        'currency' => 'USD',
    ]);

    $service = app(CreateInstructorLedgerEntries::class);

    $period = Carbon::parse('2026-02-01');

    $service->execute($subscription, $period);
    $service->execute($subscription, $period);

    expect($subscription->ledgerEntries()->count())->toBe(1);
});

it('does not create a ledger entry outside the subscription duration', function () {
    $plan = Plan::factory()->create([
        'duration_months' => 3,
    ]);

    $subscription = Subscription::factory()->create([
        'plan_id' => $plan->id,
        'starts_at' => Carbon::parse('2026-01-15'),
        'ends_at' => Carbon::parse('2026-04-15'),
    ]);

    $instructor = Instructor::factory()->create();

    RevenueAllocation::factory()->create([
        'subscription_id' => $subscription->id,
        'instructor_id' => $instructor->id,
        'amount_minor' => 1000,
        'currency' => 'USD',
    ]);

    app(CreateInstructorLedgerEntries::class)->execute(
        $subscription,
        Carbon::parse('2026-04-01'),
    );

    expect($subscription->ledgerEntries()->count())->toBe(0);
});