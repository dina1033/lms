<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\CreateInstructorLedgerEntries;
use App\Services\CreatePlatformLedgerEntries;
use Illuminate\Console\Command;

class AccrueInstructorRevenue extends Command
{
    protected $signature = 'revenue:accrue';

    protected $description = 'Accrue instructor and platform revenue for the previous month';

    public function __construct(
        private CreateInstructorLedgerEntries $instructorLedgerService,
        private CreatePlatformLedgerEntries $platformLedgerService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $period = now()->subMonth()->startOfMonth();

        Subscription::query()
            ->with(['plan', 'revenueAllocations'])
            ->where('starts_at', '<=', $period->copy()->endOfMonth())
            ->where('ends_at', '>=', $period->copy()->startOfMonth())
            ->chunkById(500, function ($subscriptions) use ($period) {
                foreach ($subscriptions as $subscription) {
                    $this->instructorLedgerService->execute(
                        $subscription,
                        $period,
                    );

                    $this->platformLedgerService->execute(
                        $subscription,
                        $period,
                    );
                }
            });

        $this->info(sprintf(
            'Instructor and platform revenue accrued for %s.',
            $period->format('Y-m'),
        ));

        return self::SUCCESS;
    }
}