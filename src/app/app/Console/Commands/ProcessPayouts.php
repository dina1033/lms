<?php

namespace App\Console\Commands;

use App\Models\Instructor;
use App\Models\LedgerEntry;
use App\Enums\LedgerEntryType;
use App\Queries\PayoutEligibilityQuery;
use App\Services\CreateInstructorPayout;
use Illuminate\Console\Command;
use App\Jobs\ProcessInstructorPayoutJob;

class ProcessPayouts extends Command
{
    protected $signature = 'payouts:process';

    protected $description = 'Process instructor payouts';

    public function __construct(
        private PayoutEligibilityQuery $eligibilityQuery,
        private CreateInstructorPayout $createInstructorPayout,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $periodStart = now()->subMonth()->startOfMonth();
        $periodEnd = now()->subMonth()->endOfMonth();

        $instructorIds = LedgerEntry::query()
            ->whereBetween('occurred_at', [$periodStart, $periodEnd])
            ->where('type', LedgerEntryType::REVENUE)
            ->whereDoesntHave('payoutItem')
            ->distinct()
            ->pluck('instructor_id');

        foreach ($instructorIds as $instructorId) {
            $instructor = Instructor::find($instructorId);

            if (! $instructor) {
                continue;
            }

            try {
                $payout = $this->createInstructorPayout->execute(
                    $instructor,
                    $periodStart->toDateTimeString(),
                    $periodEnd->toDateTimeString(),
                );
                ProcessInstructorPayoutJob::dispatch($payout->id);
            } catch (\RuntimeException $e) {
                $this->error(
                    "Instructor {$instructor->id}: {$e->getMessage()}"
                );
            }
        }

        return self::SUCCESS;
    }
}