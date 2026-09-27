<?php

namespace Database\Factories;

use App\Enums\RefundStatus;
use App\Models\Refund;
use App\Models\SubscriptionPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Refund>
 */
class RefundFactory extends Factory
{
    protected $model = Refund::class;

    public function definition(): array
    {
        return [
            'subscription_payment_id' => SubscriptionPayment::factory(),
            'amount_minor' => 5_000,
            'currency' => 'USD',
            'provider_reference' => null,
            'idempotency_key' => fake()->unique()->uuid(),
            'status' => RefundStatus::PENDING,
            'refunded_at' => null,
        ];
    }
}