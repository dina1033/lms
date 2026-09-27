<?php

namespace Database\Factories;

use App\Models\Instructor;
use App\Models\RevenueAllocation;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class RevenueAllocationFactory extends Factory
{
    protected $model = RevenueAllocation::class;

    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'instructor_id' => Instructor::factory(),
            'percentage' => 100,
            'amount_minor' => 10_000,
            'currency' => 'USD',
        ];
    }
}