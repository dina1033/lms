<?php

use App\Models\Instructor;
use App\Models\Plan;
use App\Models\RevenueAllocation;
use App\Models\Subscription;
use App\Models\User;
use App\Services\CreateInstructorLedgerEntries;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

it('accrues revenue for subscriptions active during the previous month', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-01 00:00:00'));

    $plan = Plan::factory()->create([
        'duration_months' => 3,
    ]);

    $user = User::factory()->create();

    $subscription = Subscription::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'starts_at' => Carbon::parse('2026-07-15'),
        'ends_at' => Carbon::parse('2026-10-15'),
    ]);

    $instructor = Instructor::factory()->create();

    RevenueAllocation::factory()->create([
        'subscription_id' => $subscription->id,
        'instructor_id' => $instructor->id,
        'amount_minor' => 9_000,
        'currency' => 'USD',
    ]);

    $this->artisan('revenue:accrue')
        ->assertExitCode(0);

    $entry = $subscription->ledgerEntries()->first();

    expect($entry)->not->toBeNull()
        ->and($entry->amount_minor)->toBe(3_000)
        ->and($entry->occurred_at->format('Y-m-d'))
        ->toBe('2026-09-30')
        ->and($entry->idempotency_key)
        ->toBe(
            "revenue:subscription:{$subscription->id}:instructor:{$instructor->id}:2026-09"
        );
});

it('does not accrue subscriptions that have not started yet', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-01 00:00:00'));

    $plan = Plan::factory()->create([
        'duration_months' => 3,
    ]);

    $subscription = Subscription::factory()->create([
        'plan_id' => $plan->id,
        'starts_at' => Carbon::parse('2026-10-15'),
        'ends_at' => Carbon::parse('2027-01-15'),
    ]);

    $instructor = Instructor::factory()->create();

    RevenueAllocation::factory()->create([
        'subscription_id' => $subscription->id,
        'instructor_id' => $instructor->id,
        'amount_minor' => 9_000,
        'currency' => 'USD',
    ]);

    $this->artisan('revenue:accrue')
        ->assertExitCode(0);

    expect($subscription->ledgerEntries()->count())->toBe(0);
});

it('does not accrue subscriptions that ended before the previous month', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-01 00:00:00'));

    $plan = Plan::factory()->create([
        'duration_months' => 3,
    ]);

    $subscription = Subscription::factory()->create([
        'plan_id' => $plan->id,
        'starts_at' => Carbon::parse('2026-01-15'),
        'ends_at' => Carbon::parse('2026-08-31'),
    ]);

    $instructor = Instructor::factory()->create();

    RevenueAllocation::factory()->create([
        'subscription_id' => $subscription->id,
        'instructor_id' => $instructor->id,
        'amount_minor' => 9_000,
        'currency' => 'USD',
    ]);

    $this->artisan('revenue:accrue')
        ->assertExitCode(0);

    expect($subscription->ledgerEntries()->count())->toBe(0);
});

it('does not create duplicate ledger entries when the command runs twice', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-01 00:00:00'));

    $plan = Plan::factory()->create([
        'duration_months' => 3,
    ]);

    $subscription = Subscription::factory()->create([
        'plan_id' => $plan->id,
        'starts_at' => Carbon::parse('2026-07-15'),
        'ends_at' => Carbon::parse('2026-10-15'),
    ]);

    $instructor = Instructor::factory()->create();

    RevenueAllocation::factory()->create([
        'subscription_id' => $subscription->id,
        'instructor_id' => $instructor->id,
        'amount_minor' => 9_000,
        'currency' => 'USD',
    ]);

    $this->artisan('revenue:accrue')
        ->assertExitCode(0);

    $this->artisan('revenue:accrue')
        ->assertExitCode(0);

        expect(
            $subscription->ledgerEntries()
                ->where('instructor_id', '!=', null)
                ->count()
        )->toBe(1);
});