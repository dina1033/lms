<?php

use App\Enums\LedgerEntryType;
use App\Enums\SubscriptionStatus;
use App\Models\Instructor;
use App\Models\Plan;
use App\Models\Subscription;
use Symfony\Component\Process\Process;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);


it('creates ledger entries only once when two workers run the command concurrently', function () {
    $plan = Plan::factory()->create([
        'type' => 'three_month',
        'price_minor' => 10_000,
        'currency' => 'USD',
        'duration_months' => 3,
    ]);

    $subscription = Subscription::factory()->create([
        'plan_id' => $plan->id,
        'amount_minor' => 10_000,
        'currency' => 'USD',
        'status' => SubscriptionStatus::ACTIVE,
        'starts_at' => '2026-07-01 00:00:00',
        'ends_at' => '2026-10-01 00:00:00',
    ]);

    $instructors = Instructor::factory()->count(3)->create();

    foreach ($instructors as $instructor) {
        $subscription->instructors()->attach($instructor->id);
    }

    $subscription->refresh();

    app(\App\Services\RevenueAllocationService::class)
        ->allocate($subscription);

    $allocations = $subscription->revenueAllocations()
    ->orderBy('instructor_id')
    ->get();

    expect($subscription->revenueAllocations()->count())->toBe(3);

    $workerScript = base_path(
        'tests/Support/accrue-instructor-revenue-worker.php'
    );

    $processes = [
        new Process([
            PHP_BINARY,
            $workerScript,
            (string) $subscription->id,
        ], base_path()),

        new Process([
            PHP_BINARY,
            $workerScript,
            (string) $subscription->id,
        ], base_path()),
    ];

    foreach ($processes as $process) {
        $process->start();
    }

    foreach ($processes as $index => $process) {
        $process->wait();

        expect($process->isSuccessful())
            ->toBeTrue(
                "Worker {$index} failed.\n"
                . "STDERR:\n{$process->getErrorOutput()}\n"
                . "STDOUT:\n{$process->getOutput()}"
            );
    }

    $entries = $subscription->ledgerEntries()
    ->where('type', LedgerEntryType::REVENUE)
    ->whereNotNull('instructor_id')
    ->get();

    $platformEntries = $subscription->ledgerEntries()
        ->where('type', LedgerEntryType::REVENUE)
        ->whereNull('instructor_id')
        ->get();

    expect($platformEntries)->toHaveCount(1)
        ->and($platformEntries->sum('amount_minor'))->toBe(666);

    expect($entries)->toHaveCount(3);
    expect($entries->sum('amount_minor'))->toBe(2_666);

    expect(
        $entries->pluck('idempotency_key')->unique()->count()
    )->toBe(3);
});