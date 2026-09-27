<?php

use App\Enums\LedgerEntryType;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\CreatePlatformLedgerEntries;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

it('accrues platform revenue monthly', function () {
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

    $service = app(CreatePlatformLedgerEntries::class);

    $service->execute(
        $subscription,
        Carbon::parse('2026-01-01'),
    );

    $entry = $subscription->ledgerEntries()->first();

    expect($entry)
        ->not->toBeNull()
        ->and($entry->instructor_id)->toBeNull()
        ->and($entry->type)->toBe(LedgerEntryType::REVENUE)
        ->and($entry->amount_minor)->toBe(200)
        ->and($entry->currency)->toBe('USD');
});