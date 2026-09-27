<?php

use App\Enums\LedgerEntryType;
use App\Enums\PayoutStatus;
use App\Models\Instructor;
use App\Models\LedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use App\Jobs\ProcessInstructorPayoutJob;

uses(RefreshDatabase::class);

it('creates pending payouts for instructors with payable balances', function () {
    Queue::fake();
    $instructor = Instructor::factory()->create();

    LedgerEntry::factory()->create([
        'instructor_id' => $instructor->id,
        'type' => LedgerEntryType::REVENUE,
        'amount_minor' => 3000,
        'currency' => 'USD',
        'occurred_at' => now()->subDays(10),
    ]);

    LedgerEntry::factory()->create([
        'instructor_id' => $instructor->id,
        'type' => LedgerEntryType::REVENUE,
        'amount_minor' => 5000,
        'currency' => 'USD',
        'occurred_at' => now()->subDays(5),
    ]);

    $this->artisan('payouts:process')
        ->assertSuccessful();
        
    Queue::assertPushed(
        ProcessInstructorPayoutJob::class,
    );

    $payout = \App\Models\Payout::query()
        ->where('instructor_id', $instructor->id)
        ->first();

    expect($payout)->not->toBeNull();

    expect($payout->amount_minor)
        ->toBe(8000);

    expect($payout->currency)
        ->toBe('USD');

    expect($payout->status)
        ->toBe(PayoutStatus::PENDING);

    expect($payout->items)
        ->toHaveCount(2);
});

it('processes the created payout through the payout provider', function () {
    $instructor = Instructor::factory()->create();

    LedgerEntry::factory()->create([
        'instructor_id' => $instructor->id,
        'type' => LedgerEntryType::REVENUE,
        'amount_minor' => 8000,
        'currency' => 'USD',
        'occurred_at' => now()->subDays(5),
    ]);

    $provider = new \Tests\Fakes\FakePayoutProvider(
        new \App\Contracts\PayoutResult(
            status: 'success',
            providerReference: 'command-provider-ref',
        ),
    );

    $this->app->instance(
        \App\Contracts\PayoutProvider::class,
        $provider
    );

    $this->artisan('payouts:process')
        ->assertSuccessful();

    $payout = \App\Models\Payout::query()
        ->where('instructor_id', $instructor->id)
        ->firstOrFail();

    expect($payout->status)
        ->toBe(PayoutStatus::PAID);

    expect($payout->provider_payout_reference)
        ->toBe('command-provider-ref');

    expect($provider->calls)
        ->toBe(1);
});