<?php

namespace App\Jobs;

use App\Models\Payout;
use App\Services\ProcessInstructorPayout;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessInstructorPayoutJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $payoutId,
    ) {}

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(
        ProcessInstructorPayout $processor,
    ): void {
        $payout = Payout::findOrFail($this->payoutId);

        $processor->execute($payout);
    }
}