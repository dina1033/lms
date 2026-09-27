<?php

namespace Database\Factories;

use App\Enums\PlanType;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'type' => PlanType::MONTHLY,
            'price_minor' => 10000,
            'currency' => 'USD',
            'duration_months' => 1,
        ];
    }

    public function monthly(): static
    {
        return $this->state([
            'type' => PlanType::MONTHLY,
            'duration_months' => 1,
        ]);
    }

    public function threeMonth(): static
    {
        return $this->state([
            'type' => PlanType::THREE_MONTH,
            'duration_months' => 3,
        ]);
    }

    public function annual(): static
    {
        return $this->state([
            'type' => PlanType::ANNUAL,
            'duration_months' => 12,
        ]);
    }
}