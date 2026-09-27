<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        $startsAt = now();

        return [
            'user_id' => User::factory(),
            'plan_id' => Plan::factory(),
            'idempotency_key' => fake()->unique()->uuid(),
            'amount_minor' => 10_000,
            'currency' => 'USD',
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMonth(),
        ];
    }
}