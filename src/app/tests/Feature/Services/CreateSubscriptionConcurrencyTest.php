<?php

namespace Tests\Feature\Services;

use App\Enums\SubscriptionPaymentStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Symfony\Component\Process\Process;

uses(DatabaseMigrations::class);

it('creates only one subscription when two workers use the same idempotency key concurrently', function () {
    $user = User::factory()->create();

    $plan = Plan::factory()->monthly()->create();

    $root = base_path();

    $scriptPath = storage_path(
        'framework/testing/concurrent-subscription-worker.php'
    );

    $logPath = storage_path(
        'framework/testing/concurrent-subscription.log'
    );

    $barrierPath = storage_path(
        'framework/testing/subscription-concurrency-barrier'
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

$userId = (int) $argv[2];
$planId = (int) $argv[3];
$idempotencyKey = $argv[4];
$worker = $argv[5];
$logPath = $argv[6];
$barrierPath = $argv[7];

$provider = new class($logPath, $worker) implements \App\Contracts\PaymentProvider {
    public function __construct(
        private string $logPath,
        private string $worker,
    ) {}

    public function charge(
        \App\Models\SubscriptionPayment $payment,
    ): \App\Contracts\PaymentResult {
        file_put_contents(
            $this->logPath,
            $this->worker . ':provider_called' . PHP_EOL,
            FILE_APPEND
        );

        return new \App\Contracts\PaymentResult(
            status: \App\Enums\SubscriptionPaymentStatus::PAID,
            providerReference: 'concurrent-provider-ref',
        );
    }
};

try {
    /*
     * Barrier:
     * Both workers must arrive here before either one
     * starts executing CreateSubscription.
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

    $service = new \App\Services\CreateSubscription($provider);

    $subscription = $service->execute(
        user: \App\Models\User::findOrFail($userId),
        plan: \App\Models\Plan::findOrFail($planId),
        idempotencyKey: $idempotencyKey,
    );

    file_put_contents(
        $logPath,
        $worker . ':subscription:' . $subscription->id . PHP_EOL,
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

    $idempotencyKey = 'concurrent-subscription-key';

    $workerA = new Process([
        PHP_BINARY,
        $scriptPath,
        $root,
        (string) $user->id,
        (string) $plan->id,
        $idempotencyKey,
        'worker-a',
        $logPath,
        $barrierPath,
    ]);

    $workerB = new Process([
        PHP_BINARY,
        $scriptPath,
        $root,
        (string) $user->id,
        (string) $plan->id,
        $idempotencyKey,
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
});