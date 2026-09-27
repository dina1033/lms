<?php

namespace App\Services;

use App\Contracts\PaymentProvider;
use App\Enums\SubscriptionPaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use RuntimeException;

class CreateSubscription
{
    public function __construct(
        private PaymentProvider $paymentProvider,
    ) {}

    public function execute(User $user,Plan $plan,string $idempotencyKey,): Subscription {
        try {
            $subscription = DB::transaction(function () use ($user,$plan,$idempotencyKey,) {
                $existing = Subscription::query()->where('idempotency_key', $idempotencyKey)->first();
        
                if ($existing) {
                    return $existing;
                }
        
                $startsAt = now();
        
                $subscription = Subscription::create([
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'amount_minor' => $plan->price_minor,
                    'currency' => $plan->currency,
                    'status' => SubscriptionStatus::PENDING,
                    'starts_at' => $startsAt,
                    'ends_at' => $startsAt->copy()->addMonths(
                        $plan->duration_months
                    ),
                    'idempotency_key' => $idempotencyKey,
                ]);
        
                SubscriptionPayment::create([
                    'subscription_id' => $subscription->id,
                    'amount_minor' => $subscription->amount_minor,
                    'currency' => $subscription->currency,
                    'status' => SubscriptionPaymentStatus::PENDING,
                    'idempotency_key' => 'subscription-payment-' . $subscription->id,
                ]);
        
                return $subscription;
            });
        } catch (QueryException $e) {
            if ((int) $e->errorInfo[1] !== 1062) {
                throw $e;
            }
        
            $subscription = Subscription::query()
                ->where('idempotency_key', $idempotencyKey)
                ->firstOrFail();
        }
    
        [$payment, $shouldProcess] = DB::transaction(function () use ($subscription) {
            $payment = SubscriptionPayment::query()
                ->where('subscription_id', $subscription->id)
                ->lockForUpdate()
                ->firstOrFail();
        
            if ($payment->status !== SubscriptionPaymentStatus::PENDING) {
                return [$payment, false];
            }
        
            $payment->update([
                'status' => SubscriptionPaymentStatus::PROCESSING,
                'processing_started_at' => now(),
            ]);
        
            return [$payment->fresh(), true];
        });
        
        if (! $shouldProcess) {
            return $subscription->fresh();
        }
    
        $result = $this->paymentProvider->charge($payment);
    
        return DB::transaction(function () use ($subscription,$payment,$result) {
            $subscription = Subscription::query()
                ->lockForUpdate()
                ->findOrFail($subscription->id);
        
            $payment = SubscriptionPayment::query()
                ->lockForUpdate()
                ->findOrFail($payment->id);

            if ($payment->status !== SubscriptionPaymentStatus::PROCESSING) {
                return $subscription->fresh();
            }
        
            if ($result->isSuccessful()) {
                $payment->update([
                    'status' => SubscriptionPaymentStatus::PAID,
                    'provider_reference' => $result->providerReference,
                    'paid_at' => now(),
                ]);
        
                $subscription->update([
                    'status' => SubscriptionStatus::ACTIVE,
                ]);
            } elseif ($result->isFailed()) {
                $payment->update([
                    'status' => SubscriptionPaymentStatus::FAILED,
                    'provider_reference' => $result->providerReference,
                    'failure_reason' => $result->message,
                ]);
            } else {
                $payment->update([
                    'status' => SubscriptionPaymentStatus::UNKNOWN,
                    'provider_reference' => $result->providerReference,
                    'failure_reason' => $result->message,
                ]);
            }
        
            return $subscription->fresh();
        });
    }
}