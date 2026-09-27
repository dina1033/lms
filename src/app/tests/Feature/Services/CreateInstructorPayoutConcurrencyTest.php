<?php

use App\Enums\LedgerEntryType;
use App\Enums\PayoutStatus;
use App\Models\Instructor;
use App\Models\LedgerEntry;
use App\Models\Payout;
use Symfony\Component\Process\Process;
use Illuminate\Foundation\Testing\DatabaseTruncation;
uses(DatabaseTruncation::class);

it('creates only one payout when two workers process the same period concurrently', function () {
    $instructor = Instructor::factory()->create();

    LedgerEntry::factory()->create([
        'instructor_id' => $instructor->id,
        'type' => LedgerEntryType::REVENUE,
        'amount_minor' => 3_000,
        'currency' => 'USD',
        'occurred_at' => '2026-09-10 12:00:00',
    ]);

    LedgerEntry::factory()->create([
        'instructor_id' => $instructor->id,
        'type' => LedgerEntryType::REVENUE,
        'amount_minor' => 5_000,
        'currency' => 'USD',
        'occurred_at' => '2026-09-20 12:00:00',
    ]);

    $root = base_path();

    $workerScript = <<<'PHP'
<?php

require $argv[1] . '/vendor/autoload.php';

$app = require $argv[1] . '/bootstrap/app.php';

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$instructorId = (int) $argv[2];
$worker = $argv[3];
$logPath = $argv[4];

try {
    $instructor = \App\Models\Instructor::findOrFail($instructorId);

    $service = app(\App\Services\CreateInstructorPayout::class);

    $payout = $service->execute(
        $instructor,
        '2026-09-01 00:00:00',
        '2026-09-30 23:59:59',
    );

    file_put_contents(
        $logPath,
        $worker . ':success:' . $payout->id . PHP_EOL,
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

    $scriptPath = storage_path(
        'framework/testing/create-payout-concurrency-worker.php'
    );

    $logPath = storage_path(
        'framework/testing/create-payout-concurrency.log'
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
        (string) $instructor->id,
        'worker-a',
        $logPath,
    ]);

    $workerB = new Process([
        PHP_BINARY,
        $scriptPath,
        $root,
        (string) $instructor->id,
        'worker-b',
        $logPath,
    ]);

    $workerA->start();
    $workerB->start();

    $workerA->wait();
    $workerB->wait();

    expect(
        $workerA->isSuccessful(),
        $workerA->getErrorOutput() . PHP_EOL . $workerA->getOutput()
    )->toBeTrue();
    
    expect(
        $workerB->isSuccessful(),
        $workerB->getErrorOutput() . PHP_EOL . $workerB->getOutput()
    )->toBeTrue();

    $lines = file(
        $logPath,
        FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
    );

    $successes = array_filter(
        $lines,
        fn (string $line) => str_contains($line, ':success:')
    );

    expect($successes)->toHaveCount(2);

    $payouts = Payout::query()
        ->where('instructor_id', $instructor->id)
        ->get();

    expect($payouts->count())->toBe(1);

    $payout = $payouts->first();

    expect($payout->amount_minor)
        ->toBe(8_000);

    expect($payout->status)
        ->toBe(PayoutStatus::PENDING);

    expect($payout->items()->count())
        ->toBe(2);

    expect(
        (int) $payout->items()->sum('amount_minor')
    )->toBe(8_000);
});