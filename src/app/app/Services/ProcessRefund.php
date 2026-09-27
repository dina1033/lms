<?php

namespace App\Services;

use App\Contracts\RefundProvider;
use App\Enums\RefundStatus;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessRefund
{
    public function __construct(
        private RefundProvider $provider,
    ) {}

    public function execute(Refund $refund): Refund
    {
        $shouldProcess = DB::transaction(function () use ($refund) {
            $refund = Refund::query()
                ->whereKey($refund->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($refund->status, [
                RefundStatus::SUCCEEDED,
                RefundStatus::FAILED,
                RefundStatus::UNKNOWN,
                RefundStatus::PROCESSING,
            ], true)) {
                return false;
            }

            $refund->update([
                'status' => RefundStatus::PROCESSING,
            ]);

            return true;
        });

        if (! $shouldProcess) {
            return $refund->refresh();
        }

        try {
            $result = $this->provider->refund($refund);

            $refund->update([
                'status' => match ($result->status) {
                    'succeeded' => RefundStatus::SUCCEEDED,
                    'failed' => RefundStatus::FAILED,
                    default => RefundStatus::UNKNOWN,
                },
                'provider_reference' => $result->providerReference,
                'failure_reason' => $result->failureReason,
                'refunded_at' => $result->status === 'succeeded'
                    ? now()
                    : null,
            ]);
        } catch (Throwable $e) {
            $refund->update([
                'status' => RefundStatus::UNKNOWN,
                'failure_reason' => $e->getMessage(),
            ]);
        }

        return $refund->refresh();
    }

    public function reconcile(Refund $refund): Refund
    {
        $refund = DB::transaction(function () use ($refund) {
            return Refund::query()
                ->whereKey($refund->id)
                ->lockForUpdate()
                ->firstOrFail();
        });

        if ($refund->status !== RefundStatus::UNKNOWN) {
            return $refund->refresh();
        }

        $result = $this->provider->checkStatus($refund);

        $refund->update([
            'status' => match ($result->status) {
                'succeeded' => RefundStatus::SUCCEEDED,
                'failed' => RefundStatus::FAILED,
                default => RefundStatus::UNKNOWN,
            },
            'provider_reference' => $result->providerReference
                ?? $refund->provider_reference,
            'failure_reason' => $result->failureReason,
            'refunded_at' => $result->status === 'succeeded'
                ? now()
                : null,
        ]);

        return $refund->refresh();
    }
}