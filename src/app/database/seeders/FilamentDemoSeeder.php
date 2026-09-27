<?php

namespace Database\Seeders;

use App\Enums\LedgerEntryType;
use App\Enums\PayoutStatus;
use App\Models\Instructor;
use App\Models\LedgerEntry;
use App\Models\Payout;
use App\Models\PayoutItem;
use Illuminate\Database\Seeder;

class FilamentDemoSeeder extends Seeder
{
    public function run(): void
    {
        $instructor = Instructor::create([
            'name' => 'Ahmed Hassan',
            'email' => 'ahmed@example.com',
            'payout_percentage' => 80,
            'status' => 'active',
        ]);

        $ledgerEntry1 = LedgerEntry::create([
            'instructor_id' => $instructor->id,
            'subscription_id' => null,
            'type' => LedgerEntryType::REVENUE,
            'amount_minor' => 2000,
            'currency' => 'USD',
            'occurred_at' => now()->subMonths(2),
            'source_type' => 'demo',
            'source_id' => 1,
            'idempotency_key' => 'demo-revenue-1',
        ]);

        $ledgerEntry2 = LedgerEntry::create([
            'instructor_id' => $instructor->id,
            'subscription_id' => null,
            'type' => LedgerEntryType::REVENUE,
            'amount_minor' => 3000,
            'currency' => 'USD',
            'occurred_at' => now()->subMonth(),
            'source_type' => 'demo',
            'source_id' => 2,
            'idempotency_key' => 'demo-revenue-2',
        ]);

        $payout = Payout::create([
            'instructor_id' => $instructor->id,
            'period_start' => now()->subMonths(2)->startOfMonth(),
            'period_end' => now()->subMonths(2)->endOfMonth(),
            'amount_minor' => 2000,
            'currency' => 'USD',
            'status' => PayoutStatus::PAID,
            'provider' => 'mock',
            'provider_payout_reference' => 'demo-payout-1',
            'idempotency_key' => 'demo-payout-1',
            'processed_at' => now()->subMonth(),
            'paid_at' => now()->subMonth(),
        ]);

        PayoutItem::create([
            'payout_id' => $payout->id,
            'ledger_entry_id' => $ledgerEntry1->id,
            'amount_minor' => 2000,
        ]);
    }
}