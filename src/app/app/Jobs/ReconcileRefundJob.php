<?php

namespace App\Jobs;

use App\Models\Refund;
use App\Services\ProcessRefund;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ReconcileRefundJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $refundId,
    ) {}

    public function handle(ProcessRefund $service): void
    {
        $refund = Refund::query()->findOrFail($this->refundId);

        $service->reconcile($refund);
    }
}