<?php

use App\Contracts\PayoutProvider;
use App\Contracts\PayoutResult;
use App\Enums\LedgerEntryType;
use App\Enums\PayoutStatus;
use App\Jobs\ReconcileInstructorPayoutJob;
use App\Models\Instructor;
use App\Models\LedgerEntry;
use App\Services\CreateInstructorPayout;
use App\Services\ProcessInstructorPayout;
use Tests\Fakes\FakePayoutProvider;
use Illuminate\Foundation\Testing\DatabaseMigrations;
uses(DatabaseMigrations::class);

it('reconciles an unknown payout without sending it again', function () {
    $instructor = Instructor::factory()->create();

    LedgerEntry::factory()->create([
        'instructor_id' => $instructor->id,
        'type' => LedgerEntryType::REVENUE,
        'amount_minor' => 8000,
        'currency' => 'USD',
        'occurred_at' => now()->subDays(5),
    ]);

    $payout = app(CreateInstructorPayout::class)->execute(
        $instructor,
        now()->subMonth()->toDateTimeString(),
        now()->toDateTimeString(),
    );

    $provider = new FakePayoutProvider(
        new PayoutResult(
            status: 'unknown',
        ),
        new PayoutResult(
            status: 'success',
            providerReference: 'reconciled-provider-ref',
        ),
    );

    $this->app->instance(
        PayoutProvider::class,
        $provider,
    );

    $payout->update([
        'status' => PayoutStatus::UNKNOWN,
    ]);

    (new ReconcileInstructorPayoutJob($payout->id))
        ->handle(app(ProcessInstructorPayout::class));

    $payout->refresh();

    expect($payout->status)
        ->toBe(PayoutStatus::PAID);

    expect($provider->calls)
        ->toBe(0);

    expect($provider->statusChecks)
        ->toBe(1);

    expect($payout->provider_payout_reference)
        ->toBe('reconciled-provider-ref');
});

it('keeps the payout unknown when reconciliation is still inconclusive', function () {
    $instructor = Instructor::factory()->create();

    LedgerEntry::factory()->create([
        'instructor_id' => $instructor->id,
        'type' => LedgerEntryType::REVENUE,
        'amount_minor' => 8000,
        'currency' => 'USD',
        'occurred_at' => now()->subDays(5),
    ]);

    $payout = app(CreateInstructorPayout::class)->execute(
        $instructor,
        now()->subMonth()->toDateTimeString(),
        now()->toDateTimeString(),
    );

    $provider = new FakePayoutProvider(
        new PayoutResult(
            status: 'success',
            providerReference: 'send-provider-ref',
        ),
        new PayoutResult(
            status: 'unknown',
            message: 'Still processing',
        ),
    );

    $this->app->instance(
        PayoutProvider::class,
        $provider,
    );

    $payout->update([
        'status' => PayoutStatus::UNKNOWN,
    ]);

    (new ReconcileInstructorPayoutJob($payout->id))
        ->handle(app(ProcessInstructorPayout::class));

    $payout->refresh();

    expect($payout->status)
        ->toBe(PayoutStatus::UNKNOWN);

    expect($provider->calls)
        ->toBe(0);

    expect($provider->statusChecks)
        ->toBe(1);

    expect($payout->paid_at)
        ->toBeNull();
});

it('marks the payout as failed when reconciliation confirms failure', function () {
    $instructor = Instructor::factory()->create();

    LedgerEntry::factory()->create([
        'instructor_id' => $instructor->id,
        'type' => LedgerEntryType::REVENUE,
        'amount_minor' => 8000,
        'currency' => 'USD',
        'occurred_at' => now()->subDays(5),
    ]);

    $payout = app(CreateInstructorPayout::class)->execute(
        $instructor,
        now()->subMonth()->toDateTimeString(),
        now()->toDateTimeString(),
    );

    $provider = new FakePayoutProvider(
        new PayoutResult(
            status: 'success',
            providerReference: 'send-provider-ref',
        ),
        new PayoutResult(
            status: 'failed',
            message: 'Provider rejected payout',
        ),
    );

    $this->app->instance(
        PayoutProvider::class,
        $provider,
    );

    $payout->update([
        'status' => PayoutStatus::UNKNOWN,
    ]);

    (new ReconcileInstructorPayoutJob($payout->id))
        ->handle(app(ProcessInstructorPayout::class));

    $payout->refresh();

    expect($payout->status)
        ->toBe(PayoutStatus::FAILED);

    expect($provider->calls)
        ->toBe(0);

    expect($provider->statusChecks)
        ->toBe(1);

    expect($payout->paid_at)
        ->toBeNull();

    expect($payout->failure_reason)
        ->toBe('Provider rejected payout');
});