<?php

namespace Tests\Feature\Queries;

use App\Enums\LedgerEntryType;
use App\Models\Instructor;
use App\Models\LedgerEntry;
use App\Enums\PayoutStatus;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

it('calculates instructor total earnings from ledger entries', function () {
    $instructor = Instructor::factory()->create();

    LedgerEntry::factory()->create([
        'instructor_id' => $instructor->id,
        'type' => LedgerEntryType::REVENUE,
        'amount_minor' => 2_667,
        'currency' => 'USD',
    ]);

    LedgerEntry::factory()->create([
        'instructor_id' => $instructor->id,
        'type' => LedgerEntryType::REVENUE,
        'amount_minor' => 2_667,
        'currency' => 'USD',
    ]);

    LedgerEntry::factory()->create([
        'instructor_id' => $instructor->id,
        'type' => LedgerEntryType::REVENUE,
        'amount_minor' => 2_666,
        'currency' => 'USD',
    ]);

    $query = app(\App\Queries\InstructorBalanceQuery::class);

    $balance = $query->forInstructor($instructor);

    expect($balance->totalEarnedMinor)
        ->toBe(8_000);
});

it('calculates total paid and outstanding balance from payout items', function () {
    $instructor = Instructor::factory()->create();

    $ledgerEntry1 = LedgerEntry::factory()->create([
        'instructor_id' => $instructor->id,
        'type' => LedgerEntryType::REVENUE,
        'amount_minor' => 5_000,
        'currency' => 'USD',
    ]);

    $ledgerEntry2 = LedgerEntry::factory()->create([
        'instructor_id' => $instructor->id,
        'type' => LedgerEntryType::REVENUE,
        'amount_minor' => 3_000,
        'currency' => 'USD',
    ]);

    $payout = \App\Models\Payout::factory()->create([
        'instructor_id' => $instructor->id,
        'amount_minor' => 3_000,
        'currency' => 'USD',
        'status' => PayoutStatus::PAID,
    ]);

    \App\Models\PayoutItem::factory()->create([
        'payout_id' => $payout->id,
        'ledger_entry_id' => $ledgerEntry1->id,
        'amount_minor' => 3_000,
    ]);

    $query = app(\App\Queries\InstructorBalanceQuery::class);

    $balance = $query->forInstructor($instructor);

    expect($balance->totalEarnedMinor)
        ->toBe(8_000);

    expect($balance->totalPaidMinor)
        ->toBe(3_000);

    expect($balance->outstandingMinor)
        ->toBe(5_000);
});