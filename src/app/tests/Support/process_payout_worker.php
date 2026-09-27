<?php

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$payoutId = (int) ($argv[1] ?? 0);

$provider = new class implements \App\Contracts\PayoutProvider {
    public function send(\App\Models\Payout $payout): \App\Contracts\PayoutResult
    {
        usleep(500000);

        file_put_contents(
            storage_path('framework/testing/payout-provider-calls-' . $payout->id),
            "called\n",
            FILE_APPEND | LOCK_EX,
        );

        return new \App\Contracts\PayoutResult(
            status: 'success',
            providerReference: 'concurrent-provider-ref',
        );
    }

    public function checkStatus(
        \App\Models\Payout $payout
    ): \App\Contracts\PayoutResult {
        return new \App\Contracts\PayoutResult(
            status: 'success',
            providerReference: 'concurrent-provider-ref',
        );
    }
};

$app->instance(
    \App\Contracts\PayoutProvider::class,
    $provider,
);

$payout = \App\Models\Payout::findOrFail($payoutId);

app(\App\Services\ProcessInstructorPayout::class)
    ->execute($payout);