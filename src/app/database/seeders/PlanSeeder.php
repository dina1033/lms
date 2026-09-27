<?php

namespace Database\Seeders;

use App\Enums\PlanType;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::updateOrCreate(
            ['type' => PlanType::MONTHLY],
            [
                'price_minor' => 10000,
                'currency' => 'USD',
                'duration_months' => 1,
            ]
        );

        Plan::updateOrCreate(
            ['type' => PlanType::THREE_MONTH],
            [
                'price_minor' => 27000,
                'currency' => 'USD',
                'duration_months' => 3,
            ]
        );

        Plan::updateOrCreate(
            ['type' => PlanType::ANNUAL],
            [
                'price_minor' => 100000,
                'currency' => 'USD',
                'duration_months' => 12,
            ]
        );
    }
}