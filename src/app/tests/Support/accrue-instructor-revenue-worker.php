<?php

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';

$app->make(\Illuminate\Contracts\Console\Kernel::class)
    ->bootstrap();

\Carbon\Carbon::setTestNow('2026-10-01 00:00:00');

$subscriptionId = (int) ($argv[1] ?? 0);

$subscription = \App\Models\Subscription::query()
    ->with(['plan', 'revenueAllocations'])
    ->findOrFail($subscriptionId);

foreach ($subscription->revenueAllocations as $allocation) {
    echo "Allocation {$allocation->id}: "
        . "instructor={$allocation->instructor_id}, "
        . "amount={$allocation->amount_minor}\n";
}

$exitCode = \Illuminate\Support\Facades\Artisan::call(
    'revenue:accrue'
);

echo \Illuminate\Support\Facades\Artisan::output();

exit($exitCode);