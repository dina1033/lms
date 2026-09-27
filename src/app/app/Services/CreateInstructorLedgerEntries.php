<?php

namespace App\Services;

use App\Enums\LedgerEntryType;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CreateInstructorLedgerEntries
{
    public function execute(
        Subscription $subscription,
        Carbon $period
    ): void {
        $allocations = $subscription->revenueAllocations()
            ->orderBy('id')
            ->get();

        if ($allocations->isEmpty()) {
            return;
        }

        $durationMonths = $subscription->plan->duration_months;

        if ($durationMonths <= 0) {
            return;
        }

        foreach ($allocations as $allocation) {
            $baseAmount = intdiv(
                $allocation->amount_minor,
                $durationMonths
            );

            $remainder = $allocation->amount_minor % $durationMonths;

            $periodNumber = $this->getPeriodNumber(
                $subscription,
                $period,
            );

            if ($periodNumber < 1 || $periodNumber > $durationMonths) {
                continue;
            }

            $amount = $baseAmount;

            if ($periodNumber <= $remainder) {
                $amount++;
            }

            if ($amount <= 0) {
                continue;
            }

            $idempotencyKey = sprintf(
                'revenue:subscription:%d:instructor:%d:%s',
                $subscription->id,
                $allocation->instructor_id,
                $period->format('Y-m'),
            );

            try {
                DB::transaction(function () use (
                    $subscription,
                    $allocation,
                    $amount,
                    $period,
                    $idempotencyKey,
                ) {
                    $subscription->ledgerEntries()->create([
                        'instructor_id' => $allocation->instructor_id,
                        'type' => LedgerEntryType::REVENUE,
                        'amount_minor' => $amount,
                        'currency' => $allocation->currency,
                        'occurred_at' => $this->periodEnd($period),
                        'source_type' => 'revenue_allocation',
                        'source_id' => $allocation->id,
                        'idempotency_key' => $idempotencyKey,
                    ]);
                });
            } catch (QueryException $e) {
                if (! $this->isDuplicateIdempotencyKey($e)) {
                    throw $e;
                }
            }
        }
    }

    private function getPeriodNumber(
        Subscription $subscription,
        Carbon $period,
    ): int {
        $subscriptionMonth = $subscription->starts_at
            ->copy()
            ->startOfMonth();

        return $subscriptionMonth->diffInMonths(
            $period->copy()->startOfMonth()
        ) + 1;
    }

    private function periodEnd(Carbon $period): Carbon
    {
        return $period->copy()->endOfMonth();
    }

    private function isDuplicateIdempotencyKey(
        QueryException $e
    ): bool {
        return $e->getCode() === '23000'
            && str_contains(
                $e->getMessage(),
                'ledger_entries_idempotency_key_unique'
            );
    }
}