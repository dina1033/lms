<?php

namespace App\Services;

use App\Contracts\PayoutProvider;
use App\Enums\PayoutStatus;
use App\Models\Payout;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProcessInstructorPayout
{
    public function __construct(
        private PayoutProvider $provider,
    ) {}

    public function execute(Payout $payout): Payout
    {
        [$payout, $shouldProcess] = DB::transaction(function () use ($payout) {
            $payout = Payout::query()
                ->lockForUpdate()
                ->findOrFail($payout->id);

            if ($payout->status === PayoutStatus::PAID) {
                return [$payout, false];
            }

            if ($payout->status === PayoutStatus::PROCESSING) {
                return [$payout, false];
            }

            if ($payout->status !== PayoutStatus::PENDING) {
                throw new RuntimeException(
                    "Payout {$payout->id} cannot be processed from status {$payout->status->value}."
                );
            }

            $payout->update([
                'status' => PayoutStatus::PROCESSING,
                'processing_started_at' => now(),
            ]);

            return [$payout->fresh(), true];
        });

        if (! $shouldProcess) {
            return $payout;
        }

        $result = $this->provider->send($payout);
        if ($result->isUnknown()) {
            $result = $this->provider->checkStatus($payout);
        }
        return DB::transaction(function () use ($payout, $result) {
            $payout = Payout::query()
                ->lockForUpdate()
                ->findOrFail($payout->id);

            if ($payout->status === PayoutStatus::PAID) {
                return $payout;
            }

            if ($result->isSuccessful()) {
                $payout->update([
                    'status' => PayoutStatus::PAID,
                    'provider_payout_reference' => $result->providerReference,
                    'processed_at' => now(),
                    'paid_at' => now(),
                ]);

                return $payout->fresh();
            }

            if ($result->isFailed()) {
                $payout->update([
                    'status' => PayoutStatus::FAILED,
                    'provider_payout_reference' => $result->providerReference,
                    'failure_reason' => $result->message,
                    'processed_at' => now(),
                ]);

                return $payout->fresh();
            }

            $payout->update([
                'status' => PayoutStatus::UNKNOWN,
                'provider_payout_reference' => $result->providerReference,
                'failure_reason' => $result->message,
            ]);

            return $payout->fresh();
        });
    }

    public function reconcile(Payout $payout): Payout
    {
        $payout = Payout::query()
            ->findOrFail($payout->id);

        if ($payout->status === PayoutStatus::PAID) {
            return $payout;
        }
        
        if ($payout->status !== PayoutStatus::UNKNOWN) {
            throw new RuntimeException(
                "Payout {$payout->id} cannot be reconciled from status {$payout->status->value}."
            );
        }
        $result = $this->provider->checkStatus($payout);

        return DB::transaction(function () use ($payout, $result) {
            $payout = Payout::query()
                ->lockForUpdate()
                ->findOrFail($payout->id);

            // Another reconciliation worker may have already resolved it.
            if ($payout->status === PayoutStatus::PAID) {
                return $payout;
            }

            if ($result->isSuccessful()) {
                $payout->update([
                    'status' => PayoutStatus::PAID,
                    'provider_payout_reference' => $result->providerReference,
                    'processed_at' => now(),
                    'paid_at' => now(),
                ]);

                return $payout->fresh();
            }

            if ($result->isFailed()) {
                $payout->update([
                    'status' => PayoutStatus::FAILED,
                    'provider_payout_reference' => $result->providerReference,
                    'failure_reason' => $result->message,
                    'processed_at' => now(),
                ]);

                return $payout->fresh();
            }

            $payout->update([
                'status' => PayoutStatus::UNKNOWN,
                'provider_payout_reference' => $result->providerReference,
                'failure_reason' => $result->message,
            ]);

            return $payout->fresh();
        });
    }
}

