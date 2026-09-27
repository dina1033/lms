<?php

namespace App\Services;

use App\Enums\PayoutStatus;
use App\Models\Instructor;
use App\Models\Payout;
use App\Queries\PayoutEligibilityQuery;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CreateInstructorPayout
{
    public function __construct(
        private PayoutEligibilityQuery $eligibilityQuery,
    ) {}

    public function execute(
        Instructor $instructor,
        string $periodStart,
        string $periodEnd,
    ): Payout {
        $idempotencyKey = sprintf(
            'payout:%d:%s:%s',
            $instructor->id,
            $periodStart,
            $periodEnd,
        );

        $existingPayout = Payout::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingPayout) {
            return $existingPayout;
        }

        return DB::transaction(function () use (
            $instructor,
            $periodStart,
            $periodEnd,
            $idempotencyKey,
        ) {
            $existingPayout = Payout::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existingPayout) {
                return $existingPayout;
            }

            $entries = $this->eligibilityQuery->forInstructor(
                $instructor,
                $periodStart,
                $periodEnd,
            );

            if ($entries->isEmpty()) {
                throw new \RuntimeException(
                    'No payable balance found.'
                );
            }

            $currency = $entries->first()->currency;

            if ($entries->contains(
                fn ($entry) => $entry->currency !== $currency
            )) {
                throw new \RuntimeException(
                    'Ledger entries contain multiple currencies.'
                );
            }

            $total = (int) $entries->sum('amount_minor');

            if ($total <= 0) {
                throw new \RuntimeException(
                    'No payable balance found.'
                );
            }

            try {
                $payout = Payout::create([
                    'instructor_id' => $instructor->id,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'amount_minor' => $total,
                    'currency' => $currency,
                    'status' => PayoutStatus::PENDING,
                    'idempotency_key' => $idempotencyKey,
                ]);
            } catch (QueryException $e) {
                if (! $this->isDuplicatePayout($e)) {
                    throw $e;
                }

                return Payout::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->firstOrFail();
            }

            foreach ($entries as $entry) {
                $payout->items()->create([
                    'ledger_entry_id' => $entry->id,
                    'amount_minor' => $entry->amount_minor,
                ]);
            }

            return $payout;
        },3);
    }

    private function isDuplicatePayout(QueryException $e): bool
    {
        return $e->getCode() === '23000'
            && (
                str_contains(
                    $e->getMessage(),
                    'payouts_idempotency_key_unique'
                )
                || str_contains(
                    $e->getMessage(),
                    'instructor_payout_period_unique'
                )
            );
    }
}