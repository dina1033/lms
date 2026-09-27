<?php

namespace Database\Factories;

use App\Enums\PayoutStatus;
use App\Models\Instructor;
use App\Models\Payout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payout>
 */
class PayoutFactory extends Factory
{
    protected $model = Payout::class;

    public function definition(): array
    {
        $periodStart = now()->startOfMonth();
        $periodEnd = now()->endOfMonth();

        return [
            'instructor_id' => Instructor::factory(),

            'period_start' => $periodStart,
            'period_end' => $periodEnd,

            'amount_minor' => 10000,
            'currency' => 'USD',

            'status' => PayoutStatus::PENDING,

            'provider' => null,
            'provider_payout_reference' => null,

            'idempotency_key' => fake()->unique()->uuid(),

            'processing_started_at' => null,
            'failure_reason' => null,

            'processed_at' => null,
            'paid_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'status' => PayoutStatus::PENDING,
            'processing_started_at' => null,
            'processed_at' => null,
            'paid_at' => null,
            'failure_reason' => null,
        ]);
    }

    public function processing(): static
    {
        return $this->state([
            'status' => PayoutStatus::PROCESSING,
            'processing_started_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->state([
            'status' => PayoutStatus::PAID,
            'processing_started_at' => now()->subMinutes(5),
            'processed_at' => now(),
            'paid_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => PayoutStatus::FAILED,
            'processing_started_at' => now()->subMinutes(5),
            'processed_at' => now(),
            'failure_reason' => 'Provider rejected the payout.',
        ]);
    }

    public function unknown(): static
    {
        return $this->state([
            'status' => PayoutStatus::UNKNOWN,
            'processing_started_at' => now()->subMinutes(5),
            'failure_reason' => 'Provider request timed out.',
        ]);
    }
}
