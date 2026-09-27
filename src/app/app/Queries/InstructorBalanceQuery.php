<?php

namespace App\Queries;

use App\Contracts\InstructorBalance;
use App\Enums\LedgerEntryType;
use App\Enums\PayoutStatus;
use App\Models\Instructor;
use App\Models\PayoutItem;

class InstructorBalanceQuery
{
    public function forInstructor(Instructor $instructor): InstructorBalance
    {
        $totalEarned = (int) $instructor->ledgerEntries()
            ->where('type', LedgerEntryType::REVENUE)
            ->sum('amount_minor');

        $totalPaid = (int) PayoutItem::query()
            ->whereHas('payout', function ($query) use ($instructor) {
                $query
                    ->where('instructor_id', $instructor->id)
                    ->where('status', PayoutStatus::PAID);
            })
            ->sum('amount_minor');

        $outstanding = max(
            0,
            $totalEarned - $totalPaid
        );

        return new InstructorBalance(
            totalEarnedMinor: $totalEarned,
            totalPaidMinor: $totalPaid,
            outstandingMinor: $outstanding,
        );
    }
}