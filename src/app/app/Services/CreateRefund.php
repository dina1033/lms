<?php

namespace App\Services;

use App\Enums\RefundStatus;
use App\Models\Refund;
use App\Models\SubscriptionPayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CreateRefund
{
    public function __construct(
        private CalculateRefundAmount $refundCalculator,
    ) {}

    public function execute(
        int $subscriptionPaymentId,
        Carbon $refundedAt,
        string $idempotencyKey,
    ): Refund {
        return DB::transaction(function () use (
            $subscriptionPaymentId,
            $refundedAt,
            $idempotencyKey,
        ) {
            $payment = SubscriptionPayment::query()
                ->with('subscription.plan')
                ->lockForUpdate()
                ->findOrFail($subscriptionPaymentId);

            $existingRefund = Refund::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingRefund) {
                return $existingRefund;
            }

            $alreadyRefunded = (int) Refund::query()
                ->where('subscription_payment_id', $payment->id)
                ->whereIn('status', [
                    RefundStatus::PENDING,
                    RefundStatus::PROCESSING,
                    RefundStatus::SUCCEEDED,
                    RefundStatus::UNKNOWN,
                ])
                ->sum('amount_minor');

            $remainingRefundable = max(
                0,
                $payment->amount_minor - $alreadyRefunded
            );

            $calculatedAmount = $this->refundCalculator->execute(
                $payment->subscription,
                $refundedAt,
            );

            $amountMinor = min(
                $calculatedAmount,
                $remainingRefundable,
            );

            return Refund::create([
                'subscription_payment_id' => $payment->id,
                'amount_minor' => $amountMinor,
                'currency' => $payment->currency,
                'idempotency_key' => $idempotencyKey,
                'status' => RefundStatus::PENDING,
            ]);
        });
    }
}