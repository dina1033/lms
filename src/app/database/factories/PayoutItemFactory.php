<?php

namespace Database\Factories;

use App\Models\LedgerEntry;
use App\Models\Payout;
use App\Models\PayoutItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class PayoutItemFactory extends Factory
{
    protected $model = PayoutItem::class;

    public function definition(): array
    {
        return [
            'payout_id' => Payout::factory(),
            'ledger_entry_id' => LedgerEntry::factory(),
            'amount_minor' => 3_000,
            'created_at' => now(),
        ];
    }
}