<?php

namespace Tests\Feature\Services;

use App\Models\Instructor;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Symfony\Component\Process\Process;

uses(DatabaseMigrations::class);

it('creates allocations only once when two workers allocate the same subscription concurrently', function () {
    $user = User::factory()->create();

    $plan = Plan::factory()->monthly()->create();

    $subscription = Subscription::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'amount_minor' => 10_000,
        'currency' => 'USD',
    ]);

    $instructors = Instructor::factory()->count(3)->create();

    $subscription->instructors()->attach(
        $instructors->pluck('id')
    );

    $root = base_path();

    $scriptPath = storage_path(
        'framework/testing/concurrent-revenue-allocation-worker.php'
    );

    $logPath = storage_path(
        'framework/testing/revenue-allocation-concurrency.log'
    );

    $barrierPath = storage_path(
        'framework/testing/revenue-allocation-concurrency-barrier'
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
    /*
     * Barrier:
     * Both workers must arrive here before either one
     * starts executing RevenueAllocationService.
     */
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

    $service = new \App\Services\RevenueAllocationService();

    $service->allocate($subscription);

    file_put_contents(
        $logPath,
        $worker . ':allocation_completed' . PHP_EOL,
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

    expect($subscription->revenueAllocations()->count())
        ->toBe(3);

    expect((int) $subscription->revenueAllocations()->sum('amount_minor'))
        ->toBe(8_000);
});