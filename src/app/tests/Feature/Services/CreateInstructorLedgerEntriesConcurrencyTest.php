<?php

namespace Tests\Feature\Services;

use App\Models\Instructor;
use App\Models\Plan;
use App\Models\RevenueAllocation;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Symfony\Component\Process\Process;
use Carbon\Carbon;

uses(DatabaseMigrations::class);

it('creates ledger entries only once when two workers process the same subscription concurrently', function () {
    $user = User::factory()->create();

    $plan = Plan::factory()->monthly()->create([
        'price_minor' => 10_000,
        'currency' => 'USD',
    ]);

    $subscription = Subscription::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'amount_minor' => 10_000,
        'currency' => 'USD',
    ]);

    $instructors = Instructor::factory()->count(3)->create();

    foreach ($instructors as $index => $instructor) {
        RevenueAllocation::factory()->create([
            'subscription_id' => $subscription->id,
            'instructor_id' => $instructor->id,
            'percentage' => 33.3333,
            'amount_minor' => match ($index) {
                0, 1 => 2_667,
                2 => 2_666,
            },
            'currency' => 'USD',
        ]);
    }

    $root = base_path();

    $scriptPath = storage_path(
        'framework/testing/concurrent-ledger-worker.php'
    );

    $logPath = storage_path(
        'framework/testing/ledger-concurrency.log'
    );

    $barrierPath = storage_path(
        'framework/testing/ledger-concurrency-barrier'
    );

    if (! is_dir(dirname($scriptPath))) {
        mkdir(dirname($scriptPath), 0777, true);
    }

    @unlink($barrierPath . '.worker-a');
    @unlink($barrierPath . '.worker-b');

    file_put_contents($logPath, '');

    $workerScript = <<<'PHP'
<?php

require $argv[1] . '/vendor/autoload.php';

$app = require $argv[1] . '/bootstrap/app.php';

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$subscriptionId = (int) $argv[2];
$worker = $argv[3];
$logPath = $argv[4];
$barrierPath = $argv[5];

try {
    file_put_contents(
        $barrierPath . '.' . $worker,
        'ready'
    );

    while (
        ! file_exists($barrierPath . '.worker-a') ||
        ! file_exists($barrierPath . '.worker-b')
    ) {
        usleep(10_000);
    }

    $subscription = \App\Models\Subscription::findOrFail(
        $subscriptionId
    );

    $service = new \App\Services\CreateInstructorLedgerEntries();

    $service->execute($subscription,\Carbon\Carbon::parse('2026-09-01'),);
    
    file_put_contents(
        $logPath,
        $worker . ':ledger_completed' . PHP_EOL,
        FILE_APPEND
    );
} catch (\Throwable $e) {
    file_put_contents(
        $logPath,
        $worker . ':error:' . $e->getMessage() . PHP_EOL,
        FILE_APPEND
    );

    exit(1);
}
PHP;

    file_put_contents($scriptPath, $workerScript);

    $workerA = new Process([
        PHP_BINARY,
        $scriptPath,
        $root,
        (string) $subscription->id,
        'worker-a',
        $logPath,
        $barrierPath,
    ]);

    $workerB = new Process([
        PHP_BINARY,
        $scriptPath,
        $root,
        (string) $subscription->id,
        'worker-b',
        $logPath,
        $barrierPath,
    ]);

    $workerA->start();
    $workerB->start();

    $workerA->wait();
    $workerB->wait();

    $logContents = file_exists($logPath)
        ? file_get_contents($logPath)
        : 'Log file does not exist';

    expect(
        $workerA->isSuccessful()
    )->toBeTrue(
        "Worker A failed.\n"
        . "STDERR:\n"
        . $workerA->getErrorOutput()
        . "\nSTDOUT:\n"
        . $workerA->getOutput()
        . "\nLOG:\n"
        . $logContents
    );

    expect(
        $workerB->isSuccessful()
    )->toBeTrue(
        "Worker B failed.\n"
        . "STDERR:\n"
        . $workerB->getErrorOutput()
        . "\nSTDOUT:\n"
        . $workerB->getOutput()
        . "\nLOG:\n"
        . $logContents
    );

    $subscription->refresh();

    expect($subscription->ledgerEntries()->count())
        ->toBe(3);

    expect((int) $subscription->ledgerEntries()->sum('amount_minor'))
        ->toBe(8_000);
});