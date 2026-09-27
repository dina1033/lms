<?php

namespace App\Queries;

use App\Enums\LedgerEntryType;
use App\Models\Instructor;
use App\Models\LedgerEntry;
use Illuminate\Database\Eloquent\Collection;

class PayoutEligibilityQuery
{
    public function forInstructor(
        Instructor $instructor,
        string $periodStart,
        string $periodEnd,
    ): Collection {
        return LedgerEntry::query()
            ->where('instructor_id', $instructor->id)
            ->where('type', LedgerEntryType::REVENUE)
            ->whereBetween('occurred_at', [$periodStart, $periodEnd])
            ->whereDoesntHave('payoutItem')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();
    }
}