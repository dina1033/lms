<?php

use App\Enums\LedgerEntryType;
use App\Models\Instructor;
use App\Models\LedgerEntry;
use App\Models\PayoutItem;
use App\Models\Payout;
use App\Queries\PayoutEligibilityQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns unpaid revenue ledger entries for the instructor within the period', function () {
    $instructor = Instructor::factory()->create();

    $eligibleEntry = LedgerEntry::factory()->create([
        'instructor_id' => $instructor->id,
        'type' => LedgerEntryType::REVENUE,
        'amount_minor' => 3_000,
        'occurred_at' => '2026-09-10 12:00:00',
    ]);

    LedgerEntry::factory()->create([
        'instructor_id' => $instructor->id,
        'type' => LedgerEntryType::REVENUE,
        'amount_minor' => 2_000,
        'occurred_at' => '2026-08-10 12:00:00',
    ]);

    LedgerEntry::factory()->create([
        'instructor_id' => $instructor->id,
        'type' => LedgerEntryType::REVENUE,
        'amount_minor' => 1_000,
        'occurred_at' => '2026-10-10 12:00:00',
    ]);

    LedgerEntry::factory()->create([
        'instructor_id' => $instructor->id,
        'type' => LedgerEntryType::REFUND,
        'amount_minor' => 500,
        'occurred_at' => '2026-09-15 12:00:00',
    ]);

    $entries = app(PayoutEligibilityQuery::class)
        ->forInstructor(
            $instructor,
            '2026-09-01 00:00:00',
            '2026-09-30 23:59:59',
        );

    expect($entries->count())->toBe(1);

    expect($entries->first()->id)
        ->toBe($eligibleEntry->id);
});

it('excludes ledger entries that already belong to a payout', function () {
    $instructor = Instructor::factory()->create();

    $paidEntry = LedgerEntry::factory()->create([
        'instructor_id' => $instructor->id,
        'type' => LedgerEntryType::REVENUE,
        'amount_minor' => 3_000,
        'occurred_at' => '2026-09-10 12:00:00',
    ]);

    PayoutItem::factory()->create([
        'ledger_entry_id' => $paidEntry->id,
        'amount_minor' => 3_000,
    ]);

    $eligibleEntry = LedgerEntry::factory()->create([
        'instructor_id' => $instructor->id,
        'type' => LedgerEntryType::REVENUE,
        'amount_minor' => 2_000,
        'occurred_at' => '2026-09-15 12:00:00',
    ]);

    $entries = app(PayoutEligibilityQuery::class)
        ->forInstructor(
            $instructor,
            '2026-09-01 00:00:00',
            '2026-09-30 23:59:59',
        );

    expect($entries->count())->toBe(1);

    expect($entries->first()->id)
        ->toBe($eligibleEntry->id);
});