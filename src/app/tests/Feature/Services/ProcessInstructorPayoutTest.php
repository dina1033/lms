<?php

use App\Contracts\PayoutResult;
use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Services\ProcessInstructorPayout;
use Tests\Fakes\FakePayoutProvider;
use Symfony\Component\Process\Process;
use Illuminate\Foundation\Testing\DatabaseMigrations;
uses(DatabaseMigrations::class);

it('processes a pending payout successfully', function () {
    $payout = Payout::factory()->pending()->create();

    $provider = new FakePayoutProvider(
        new PayoutResult(
            status: 'success',
            providerReference: 'provider-123',
        )
    );

    $service = new ProcessInstructorPayout($provider);

    $result = $service->execute($payout);

    expect($result->status)
        ->toBe(PayoutStatus::PAID);

    expect($result->provider_payout_reference)
        ->toBe('provider-123');

    expect($result->paid_at)
        ->not->toBeNull();

    expect($result->processed_at)
        ->not->toBeNull();

    expect($provider->calls)
        ->toBe(1);
});

it('marks a pending payout as failed when provider fails', function () {
    $payout = Payout::factory()->pending()->create();

    $provider = new FakePayoutProvider(
        new PayoutResult(
            status: 'failed',
            providerReference: 'provider-failed-123',
            message: 'Insufficient funds',
        )
    );

    $service = new ProcessInstructorPayout($provider);

    $result = $service->execute($payout);

    expect($result->status)
        ->toBe(PayoutStatus::FAILED);

    expect($result->provider_payout_reference)
        ->toBe('provider-failed-123');

    expect($result->failure_reason)
        ->toBe('Insufficient funds');

    expect($result->processed_at)
        ->not->toBeNull();

    expect($provider->calls)
        ->toBe(1);
});

it('marks a pending payout as unknown when provider result is unknown', function () {
    $payout = Payout::factory()->pending()->create();

    $provider = new FakePayoutProvider(
        new PayoutResult(
            status: 'unknown',
            message: 'Provider timeout',
        )
    );

    $service = new ProcessInstructorPayout($provider);

    $result = $service->execute($payout);

    expect($result->status)
        ->toBe(PayoutStatus::UNKNOWN);

    expect($result->failure_reason)
        ->toBe('Provider timeout');

    expect($result->processed_at)
        ->toBeNull();

    expect($provider->calls)
        ->toBe(1);
});

it('does not call provider when payout is already paid', function () {
    $payout = Payout::factory()->paid()->create();

    $provider = new FakePayoutProvider(
        new PayoutResult(
            status: 'success',
            providerReference: 'should-not-be-used',
        )
    );

    $service = new ProcessInstructorPayout($provider);

    $result = $service->execute($payout);

    expect($result->status)
        ->toBe(PayoutStatus::PAID);

    expect($provider->calls)
        ->toBe(0);
});

it('does not call provider when payout is already processing', function () {
    $payout = Payout::factory()->processing()->create();

    $provider = new FakePayoutProvider(
        new PayoutResult(
            status: 'success',
            providerReference: 'should-not-be-used',
        )
    );

    $service = new ProcessInstructorPayout($provider);

    $result = $service->execute($payout);

    expect($result->status)
        ->toBe(PayoutStatus::PROCESSING);

    expect($provider->calls)
        ->toBe(0);
});

it('does not overwrite an already paid payout during finalization', function () {
    $payout = Payout::factory()->paid()->create([
        'provider_payout_reference' => 'original-provider-ref',
    ]);

    $provider = new FakePayoutProvider(
        new PayoutResult(
            status: 'success',
            providerReference: 'new-provider-ref',
        )
    );

    $service = new ProcessInstructorPayout($provider);

    $result = $service->execute($payout);

    expect($result->status)
        ->toBe(PayoutStatus::PAID);

    expect($result->provider_payout_reference)
        ->toBe('original-provider-ref');

    expect($provider->calls)
        ->toBe(0);
});

it('claims a pending payout as processing', function () {
    $payout = Payout::factory()->pending()->create();

    DB::transaction(function () use ($payout) {
        $payout = Payout::query()
            ->lockForUpdate()
            ->findOrFail($payout->id);

        expect($payout->status)
            ->toBe(PayoutStatus::PENDING);

        $payout->update([
            'status' => PayoutStatus::PROCESSING,
            'processing_started_at' => now(),
        ]);
    });

    $payout->refresh();

    expect($payout->status)
        ->toBe(PayoutStatus::PROCESSING);

    expect($payout->processing_started_at)
        ->not->toBeNull();
});

it('locks and claims a pending payout', function () {
    $payout = Payout::factory()->pending()->create();

    $connectionA = DB::connection();

    $connectionA->beginTransaction();

    try {
        $lockedPayout = $connectionA
            ->table('payouts')
            ->where('id', $payout->id)
            ->lockForUpdate()
            ->first();

        expect($lockedPayout->status)
            ->toBe(PayoutStatus::PENDING->value);

        $connectionA
            ->table('payouts')
            ->where('id', $payout->id)
            ->update([
                'status' => PayoutStatus::PROCESSING->value,
                'processing_started_at' => now(),
            ]);
    } finally {
        $connectionA->commit();
    }

    $payout->refresh();

    expect($payout->status)
        ->toBe(PayoutStatus::PROCESSING);
});


it('allows only one worker to claim the same payout concurrently', function () {
    $payout = Payout::factory()->pending()->create();

    $root = base_path();

    $workerScript = <<<'PHP'
<?php

require $argv[1] . '/vendor/autoload.php';

$app = require $argv[1] . '/bootstrap/app.php';

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$payoutId = (int) $argv[2];
$worker = $argv[3];

$db = \Illuminate\Support\Facades\DB::connection();

$db->beginTransaction();

try {
    $payout = $db->table('payouts')
        ->where('id', $payoutId)
        ->lockForUpdate()
        ->first();

    file_put_contents(
        $argv[4],
        $worker . ':' . $payout->status . PHP_EOL,
        FILE_APPEND
    );

    if ($payout->status === 'pending') {
        $db->table('payouts')
            ->where('id', $payoutId)
            ->update([
                'status' => 'processing',
                'processing_started_at' => now(),
            ]);

        file_put_contents(
            $argv[4],
            $worker . ':claimed' . PHP_EOL,
            FILE_APPEND
        );
    } else {
        file_put_contents(
            $argv[4],
            $worker . ':skipped' . PHP_EOL,
            FILE_APPEND
        );
    }

    $db->commit();
} catch (\Throwable $e) {
    $db->rollBack();

    file_put_contents(
        $argv[4],
        $worker . ':error:' . $e->getMessage() . PHP_EOL,
        FILE_APPEND
    );

    exit(1);
}
PHP;

    $scriptPath = storage_path('framework/testing/concurrency-worker.php');
    $logPath = storage_path('framework/testing/concurrency.log');

    if (! is_dir(dirname($scriptPath))) {
        mkdir(dirname($scriptPath), 0777, true);
    }

    file_put_contents($scriptPath, $workerScript);
    file_put_contents($logPath, '');

    $workerA = new Process([
        PHP_BINARY,
        $scriptPath,
        $root,
        (string) $payout->id,
        'worker-a',
        $logPath,
    ]);

    $workerB = new Process([
        PHP_BINARY,
        $scriptPath,
        $root,
        (string) $payout->id,
        'worker-b',
        $logPath,
    ]);

    $workerA->start();

    usleep(100_000);

    $workerB->start();

    $workerA->wait();
    $workerB->wait();

    expect($workerA->isSuccessful())->toBeTrue();
    expect($workerB->isSuccessful())->toBeTrue();

    $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    $claims = array_filter(
        $lines,
        fn (string $line) => str_ends_with($line, ':claimed')
    );

    expect($claims)->toHaveCount(1);

    expect(
        Payout::findOrFail($payout->id)->status
    )->toBe(PayoutStatus::PROCESSING);
});


it('calls the provider only once when two workers process the same payout concurrently', function () {
    $payout = Payout::factory()->pending()->create();

    $root = base_path();

    $workerScript = <<<'PHP'
<?php

require $argv[1] . '/vendor/autoload.php';

$app = require $argv[1] . '/bootstrap/app.php';

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$payoutId = (int) $argv[2];
$logPath = $argv[3];

$provider = new class($logPath) implements \App\Contracts\PayoutProvider {
    public function __construct(
        private string $logPath,
    ) {}

    public function send(\App\Models\Payout $payout): \App\Contracts\PayoutResult
    {
        file_put_contents(
            $this->logPath,
            "provider_called:" . $payout->id . PHP_EOL,
            FILE_APPEND
        );

        usleep(300_000);

        return new \App\Contracts\PayoutResult(
            status: 'success',
            providerReference: 'concurrent-provider-ref',
        );
    }

    public function checkStatus(\App\Models\Payout $payout): \App\Contracts\PayoutResult
    {
        return new \App\Contracts\PayoutResult(
            status: 'success',
            providerReference: 'concurrent-provider-ref',
        );
    }
};

try {
    $service = new \App\Services\ProcessInstructorPayout($provider);

    $payout = \App\Models\Payout::findOrFail($payoutId);

    $result = $service->execute($payout);

    file_put_contents(
        $logPath,
        "worker_finished:" . $result->status->value . PHP_EOL,
        FILE_APPEND
    );
} catch (\Throwable $e) {
    file_put_contents(
        $logPath,
        "worker_error:" . $e->getMessage() . PHP_EOL,
        FILE_APPEND
    );

    exit(1);
}
PHP;

    $scriptPath = storage_path('framework/testing/concurrency-provider-worker.php');
    $logPath = storage_path('framework/testing/concurrency-provider.log');

    if (! is_dir(dirname($scriptPath))) {
        mkdir(dirname($scriptPath), 0777, true);
    }

    file_put_contents($scriptPath, $workerScript);
    file_put_contents($logPath, '');

    $workerA = new Process([
        PHP_BINARY,
        $scriptPath,
        $root,
        (string) $payout->id,
        $logPath,
    ]);

    $workerB = new Process([
        PHP_BINARY,
        $scriptPath,
        $root,
        (string) $payout->id,
        $logPath,
    ]);

    $workerA->start();
    $workerB->start();

    $workerA->wait();
    $workerB->wait();

    expect($workerA->isSuccessful())->toBeTrue();
    expect($workerB->isSuccessful())->toBeTrue();

    $lines = file(
        $logPath,
        FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
    );

    $providerCalls = array_filter(
        $lines,
        fn (string $line) => str_starts_with($line, 'provider_called:')
    );

    expect($providerCalls)->toHaveCount(1);

    $finalPayout = Payout::findOrFail($payout->id);

    expect($finalPayout->status)
        ->toBe(PayoutStatus::PAID);

    expect($finalPayout->provider_payout_reference)
        ->toBe('concurrent-provider-ref');
});

it('reconciles an unknown payout using the provider status check', function () {
    $payout = Payout::factory()->pending()->create();

    $provider = new FakePayoutProvider(
        new PayoutResult(
            status: 'unknown',
            message: 'Provider timeout',
        ),
        new PayoutResult(
            status: 'success',
            providerReference: 'reconciled-provider-ref',
        ),
    );

    $service = new ProcessInstructorPayout($provider);

    $result = $service->execute($payout);

    expect($result->status)
        ->toBe(PayoutStatus::PAID);

    expect($result->provider_payout_reference)
        ->toBe('reconciled-provider-ref');

    expect($provider->calls)
        ->toBe(1);

    expect($provider->statusChecks)
        ->toBe(1);
});

it('marks an unknown payout as failed when status check confirms failure', function () {
    $payout = Payout::factory()->pending()->create();

    $provider = new FakePayoutProvider(
        new PayoutResult(
            status: 'unknown',
            message: 'Provider timeout',
        ),
        new PayoutResult(
            status: 'failed',
            providerReference: 'failed-provider-ref',
            message: 'Insufficient provider balance',
        ),
    );

    $service = new ProcessInstructorPayout($provider);

    $result = $service->execute($payout);

    expect($result->status)
        ->toBe(PayoutStatus::FAILED);

    expect($result->provider_payout_reference)
        ->toBe('failed-provider-ref');

    expect($result->failure_reason)
        ->toBe('Insufficient provider balance');

    expect($result->processed_at)
        ->not->toBeNull();

    expect($result->paid_at)
        ->toBeNull();

    expect($provider->calls)
        ->toBe(1);

    expect($provider->statusChecks)
        ->toBe(1);
});

it('keeps payout unknown when status check is still unknown', function () {
    $payout = Payout::factory()->pending()->create();

    $provider = new FakePayoutProvider(
        new PayoutResult(
            status: 'unknown',
            message: 'Provider timeout',
        ),
        new PayoutResult(
            status: 'unknown',
            message: 'Status still unavailable',
        ),
    );

    $service = new ProcessInstructorPayout($provider);

    $result = $service->execute($payout);

    expect($result->status)
        ->toBe(PayoutStatus::UNKNOWN);

    expect($result->failure_reason)
        ->toBe('Status still unavailable');

    expect($result->processed_at)
        ->toBeNull();

    expect($result->paid_at)
        ->toBeNull();

    expect($provider->calls)
        ->toBe(1);

    expect($provider->statusChecks)
        ->toBe(1);
});


it('reconciles an existing unknown payout without sending the payout again', function () {
    $payout = Payout::factory()->create([
        'status' => PayoutStatus::UNKNOWN,
        'processing_started_at' => now()->subMinutes(5),
    ]);

    $provider = new FakePayoutProvider(
        new PayoutResult(
            status: 'success',
            providerReference: 'should-not-be-used',
        ),
        new PayoutResult(
            status: 'success',
            providerReference: 'reconciled-existing-ref',
        ),
    );

    $service = new ProcessInstructorPayout($provider);

    $result = $service->reconcile($payout);

    expect($result->status)
        ->toBe(PayoutStatus::PAID);

    expect($result->provider_payout_reference)
        ->toBe('reconciled-existing-ref');

    expect($provider->calls)
        ->toBe(0);

    expect($provider->statusChecks)
        ->toBe(1);
});


it('does not overwrite a payout already reconciled as paid', function () {
    $payout = Payout::factory()->create([
        'status' => PayoutStatus::UNKNOWN,
    ]);

    $provider = new FakePayoutProvider(
        new PayoutResult(
            status: 'unknown',
            message: 'Not used',
        ),
        new PayoutResult(
            status: 'success',
            providerReference: 'reconciled-ref',
        ),
    );

    $service = new ProcessInstructorPayout($provider);

    $firstResult = $service->reconcile($payout);

    expect($firstResult->status)
        ->toBe(PayoutStatus::PAID);

    $secondResult = $service->reconcile($payout->fresh());

    expect($secondResult->status)
        ->toBe(PayoutStatus::PAID);

    expect($secondResult->provider_payout_reference)
        ->toBe('reconciled-ref');
});

it('reconciles the same unknown payout safely when two workers run concurrently', function () {
    $payout = Payout::factory()->create([
        'status' => PayoutStatus::UNKNOWN,
        'processing_started_at' => now()->subMinutes(5),
    ]);

    $root = base_path();

    $workerScript = <<<'PHP'
<?php

require $argv[1] . '/vendor/autoload.php';

$app = require $argv[1] . '/bootstrap/app.php';

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$payoutId = (int) $argv[2];
$logPath = $argv[3];

$provider = new class($logPath) implements \App\Contracts\PayoutProvider {
    public function __construct(
        private string $logPath,
    ) {}

    public function send(\App\Models\Payout $payout): \App\Contracts\PayoutResult
    {
        file_put_contents(
            $this->logPath,
            "send_called:" . $payout->id . PHP_EOL,
            FILE_APPEND
        );

        return new \App\Contracts\PayoutResult(
            status: 'success',
            providerReference: 'should-not-send',
        );
    }

    public function checkStatus(\App\Models\Payout $payout): \App\Contracts\PayoutResult
    {
        file_put_contents(
            $this->logPath,
            "status_checked:" . $payout->id . PHP_EOL,
            FILE_APPEND
        );

        usleep(300_000);

        return new \App\Contracts\PayoutResult(
            status: 'success',
            providerReference: 'reconciled-concurrent-ref',
        );
    }
};

try {
    $service = new \App\Services\ProcessInstructorPayout($provider);

    $payout = \App\Models\Payout::findOrFail($payoutId);

    $result = $service->reconcile($payout);

    file_put_contents(
        $logPath,
        "worker_finished:" . $result->status->value . PHP_EOL,
        FILE_APPEND
    );
} catch (\Throwable $e) {
    file_put_contents(
        $logPath,
        "worker_error:" . $e->getMessage() . PHP_EOL,
        FILE_APPEND
    );

    exit(1);
}
PHP;

    $scriptPath = storage_path(
        'framework/testing/concurrency-reconcile-worker.php'
    );

    $logPath = storage_path(
        'framework/testing/concurrency-reconcile.log'
    );

    if (! is_dir(dirname($scriptPath))) {
        mkdir(dirname($scriptPath), 0777, true);
    }

    file_put_contents($scriptPath, $workerScript);
    file_put_contents($logPath, '');

    $workerA = new Process([
        PHP_BINARY,
        $scriptPath,
        $root,
        (string) $payout->id,
        $logPath,
    ]);

    $workerB = new Process([
        PHP_BINARY,
        $scriptPath,
        $root,
        (string) $payout->id,
        $logPath,
    ]);

    $workerA->start();
    $workerB->start();

    $workerA->wait();
    $workerB->wait();

    expect($workerA->isSuccessful())->toBeTrue();
    expect($workerB->isSuccessful())->toBeTrue();

    $lines = file(
        $logPath,
        FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
    );

    $sendCalls = array_filter(
        $lines,
        fn (string $line) => str_starts_with($line, 'send_called:')
    );

    expect($sendCalls)->toHaveCount(0);

    $finalPayout = Payout::findOrFail($payout->id);

    expect($finalPayout->status)
        ->toBe(PayoutStatus::PAID);

    expect($finalPayout->provider_payout_reference)
        ->toBe('reconciled-concurrent-ref');
});