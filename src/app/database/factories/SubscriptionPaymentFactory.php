<?php

namespace Database\Factories;

use App\Enums\SubscriptionPaymentStatus;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SubscriptionPayment>
 */
class SubscriptionPaymentFactory extends Factory
{
    protected $model = SubscriptionPayment::class;

    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'provider' => 'fake',
            'provider_reference' => null,
            'amount_minor' => 10_000,
            'currency' => 'USD',
            'status' => SubscriptionPaymentStatus::PAID,
            'failure_reason' => null,
            'idempotency_key' => fake()->unique()->uuid(),
            'processing_started_at' => null,
            'paid_at' => now(),
        ];
    }
}