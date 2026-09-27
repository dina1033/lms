<?php

namespace App\Services;

use App\Enums\LedgerEntryType;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CreatePlatformLedgerEntries
{
    public function execute(
        Subscription $subscription,
        Carbon $period,
    ): void {
        $durationMonths = $subscription->plan->duration_months;

        if ($durationMonths <= 0) {
            return;
        }

        $platformPercentage = (float) config(
            'services.platform.revenue_percentage'
        );

        $platformTotal = (int) round(
            $subscription->amount_minor
            * ($platformPercentage / 100)
        );

        $baseAmount = intdiv($platformTotal, $durationMonths);
        $remainder = $platformTotal % $durationMonths;

        $subscriptionMonth = $subscription->starts_at
            ->copy()
            ->startOfMonth();

        $periodNumber = $subscriptionMonth->diffInMonths(
            $period->copy()->startOfMonth()
        ) + 1;

        if ($periodNumber < 1 || $periodNumber > $durationMonths) {
            return;
        }

        $amount = $baseAmount;

        if ($periodNumber <= $remainder) {
            $amount++;
        }

        if ($amount <= 0) {
            return;
        }

        $idempotencyKey = sprintf(
            'platform-revenue:subscription:%d:%s',
            $subscription->id,
            $period->format('Y-m'),
        );

        try {
            DB::transaction(function () use (
                $subscription,
                $amount,
                $period,
                $idempotencyKey,
            ) {
                $subscription->ledgerEntries()->create([
                    'instructor_id' => null,
                    'type' => LedgerEntryType::REVENUE,
                    'amount_minor' => $amount,
                    'currency' => $subscription->currency,
                    'occurred_at' => $period->copy()->endOfMonth(),
                    'source_type' => 'platform_revenue_allocation',
                    'source_id' => $subscription->id,
                    'idempotency_key' => $idempotencyKey,
                ]);
            });
        } catch (QueryException $e) {
            if (
                $e->getCode() !== '23000'
                || ! str_contains(
                    $e->getMessage(),
                    'ledger_entries_idempotency_key_unique'
                )
            ) {
                throw $e;
            }
        }
    }
}