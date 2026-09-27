<?php

namespace App\Console\Commands;

use App\Models\Instructor;
use App\Queries\PayoutEligibilityQuery;
use App\Services\CreateInstructorPayout;
use Illuminate\Console\Command;
use App\Services\ProcessInstructorPayout;
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
        $periodEnd = now();
        $periodStart = now()->subMonth();

        $instructorIds = \App\Models\LedgerEntry::query()
            ->whereBetween('occurred_at', [$periodStart, $periodEnd])
            ->where('type', \App\Enums\LedgerEntryType::REVENUE)
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