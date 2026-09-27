<?php

use App\Contracts\PayoutProvider;
use App\Contracts\PayoutResult;
use App\Enums\LedgerEntryType;
use App\Enums\PayoutStatus;
use App\Jobs\ProcessInstructorPayoutJob;
use App\Models\Instructor;
use App\Models\LedgerEntry;
use App\Models\Payout;
use App\Services\CreateInstructorPayout;
use Tests\Fakes\FakePayoutProvider;

it('processes a pending payout', function () {
    $instructor = Instructor::factory()->create();

    $entry = LedgerEntry::factory()->create([
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
            providerReference: 'job-provider-ref',
        ),
    );

    $this->app->instance(
        PayoutProvider::class,
        $provider,
    );

    (new ProcessInstructorPayoutJob($payout->id))->handle(
        app(\App\Services\ProcessInstructorPayout::class),
    );

    $payout->refresh();

    expect($payout->status)->toBe(PayoutStatus::PAID);
    expect($payout->provider_payout_reference)
        ->toBe('job-provider-ref');

    expect($provider->calls)->toBe(1);
});

it('does not pay the same payout twice when the job is retried', function () {
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
            providerReference: 'retry-provider-ref',
        ),
    );

    $this->app->instance(
        PayoutProvider::class,
        $provider,
    );

    $job = new ProcessInstructorPayoutJob($payout->id);

    $job->handle(
        app(\App\Services\ProcessInstructorPayout::class),
    );

    $job->handle(
        app(\App\Services\ProcessInstructorPayout::class),
    );

    $payout->refresh();

    expect($payout->status)->toBe(PayoutStatus::PAID);
    expect($provider->calls)->toBe(1);
});