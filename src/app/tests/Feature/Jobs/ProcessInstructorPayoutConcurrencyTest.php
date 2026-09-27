<?php

use App\Enums\LedgerEntryType;
use App\Enums\PayoutStatus;
use App\Models\Instructor;
use App\Models\LedgerEntry;
use App\Models\Payout;
use Symfony\Component\Process\Process;

it('allows only one concurrent worker to send the payout', function () {
    $instructor = Instructor::factory()->create();

    LedgerEntry::factory()->create([
        'instructor_id' => $instructor->id,
        'type' => LedgerEntryType::REVENUE,
        'amount_minor' => 8000,
        'currency' => 'USD',
        'occurred_at' => now()->subDays(5),
    ]);

    $payout = app(\App\Services\CreateInstructorPayout::class)->execute(
        $instructor,
        now()->subMonth()->toDateTimeString(),
        now()->toDateTimeString(),
    );

    $payoutId = $payout->id;

    $worker = base_path(
        'tests/Support/process_payout_worker.php'
    );

    $callLog = storage_path(
        'framework/testing/payout-provider-calls-' . $payoutId
    );
    
    @unlink($callLog);

    $processA = new Process([
        PHP_BINARY,
        $worker,
        (string) $payoutId,
    ], base_path());

    $processB = new Process([
        PHP_BINARY,
        $worker,
        (string) $payoutId,
    ], base_path());

    $processA->start();
    $processB->start();

    $processA->wait();
    $processB->wait();

    expect($processA->getExitCode())
        ->toBe(0, $processA->getErrorOutput());

    expect($processB->getExitCode())
        ->toBe(0, $processB->getErrorOutput());

    $payout->refresh();

    expect($payout->status)
        ->toBe(PayoutStatus::PAID);

    $calls = file_exists($callLog)
        ? file($callLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
        : [];

    expect($calls)->toHaveCount(1);

    @unlink($callLog);
});