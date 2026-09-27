<?php

namespace Database\Factories;

use App\Enums\LedgerEntryType;
use App\Models\Instructor;
use App\Models\LedgerEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

class LedgerEntryFactory extends Factory
{
    protected $model = LedgerEntry::class;

    public function definition(): array
    {
        return [
            'subscription_id' => null,
            'instructor_id' => Instructor::factory(),
            'type' => LedgerEntryType::REVENUE,
            'amount_minor' => 10_000,
            'currency' => 'USD',
            'occurred_at' => now(),
            'source_type' => 'test',
            'source_id' => fake()->unique()->numberBetween(1, 1_000_000),
            'idempotency_key' => fake()->unique()->uuid(),
        ];
    }
}