<?php

namespace App\Services;

use App\Models\Subscription;
use Carbon\Carbon;

class CalculateRefundAmount
{
    public function execute(
        Subscription $subscription,
        Carbon $refundedAt,
    ): int {
        $totalMonths = $subscription->plan->duration_months;

        if ($totalMonths <= 0) {
            return 0;
        }

        $subscriptionStart = $subscription->starts_at->copy()->startOfMonth();
        $refundMonth = $refundedAt->copy()->startOfMonth();

        $usedMonths = $subscriptionStart->diffInMonths($refundMonth) + 1;

        $usedMonths = min($usedMonths, $totalMonths);

        $unusedMonths = $totalMonths - $usedMonths;

        if ($unusedMonths <= 0) {
            return 0;
        }

        $baseAmount = intdiv(
            $subscription->amount_minor,
            $totalMonths
        );

        $remainder = $subscription->amount_minor % $totalMonths;

        $refundAmount = 0;

        for ($month = $usedMonths + 1; $month <= $totalMonths; $month++) {
            $monthAmount = $baseAmount;

            if ($month <= $remainder) {
                $monthAmount++;
            }

            $refundAmount += $monthAmount;
        }

        return $refundAmount;
    }
}