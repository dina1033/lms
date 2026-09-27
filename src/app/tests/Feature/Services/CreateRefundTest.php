<?php

use App\Enums\RefundStatus;
use App\Models\Refund;
use App\Models\SubscriptionPayment;
use App\Services\CreateRefund;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use App\Models\Plan;
use App\Models\Subscription;
use Carbon\Carbon;

uses(DatabaseMigrations::class);

it('creates a pending refund', function () {
    $plan = Plan::factory()->annual()->create([
        'price_minor' => 12_000,
        'duration_months' => 12,
    ]);

    $subscription = Subscription::factory()->create([
        'plan_id' => $plan->id,
        'amount_minor' => 12_000,
        'starts_at' => '2026-01-10 10:00:00',
        'ends_at' => '2027-01-10 10:00:00',
    ]);

    $payment = SubscriptionPayment::factory()->create([
        'subscription_id' => $subscription->id,
        'amount_minor' => 12_000,
        'currency' => 'USD',
    ]);

    $refund = app(CreateRefund::class)->execute(
        subscriptionPaymentId: $payment->id,
        refundedAt: Carbon::parse('2026-05-15 12:00:00'),
        idempotencyKey: 'refund-test-1',
    );

    expect($refund)
        ->toBeInstanceOf(Refund::class)
        ->and($refund->subscription_payment_id)->toBe($payment->id)
        ->and($refund->amount_minor)->toBe(7_000)
        ->and($refund->currency)->toBe('USD')
        ->and($refund->status)->toBe(RefundStatus::PENDING);
});

it('returns the existing refund for the same idempotency key', function () {
    $plan = Plan::factory()->annual()->create([
        'price_minor' => 12_000,
        'duration_months' => 12,
    ]);

    $subscription = Subscription::factory()->create([
        'plan_id' => $plan->id,
        'amount_minor' => 12_000,
        'starts_at' => '2026-01-10 10:00:00',
        'ends_at' => '2027-01-10 10:00:00',
    ]);

    $payment = SubscriptionPayment::factory()->create([
        'subscription_id' => $subscription->id,
        'amount_minor' => 12_000,
        'currency' => 'USD',
    ]);

    $service = app(CreateRefund::class);

    $first = $service->execute(
        subscriptionPaymentId: $payment->id,
        refundedAt: Carbon::parse('2026-05-15 12:00:00'),
        idempotencyKey: 'refund-idempotency-1',
    );

    $second = $service->execute(
        subscriptionPaymentId: $payment->id,
        refundedAt: Carbon::parse('2026-05-15 12:00:00'),
        idempotencyKey: 'refund-idempotency-1',
    );

    expect($second->id)->toBe($first->id)
        ->and(Refund::query()->count())->toBe(1);
});

it('calculates the refund amount from the unused subscription period', function () {
    $plan = Plan::factory()->annual()->create([
        'price_minor' => 12_000,
        'duration_months' => 12,
    ]);

    $subscription = Subscription::factory()->create([
        'plan_id' => $plan->id,
        'amount_minor' => 12_000,
        'starts_at' => '2026-01-10 10:00:00',
        'ends_at' => '2027-01-10 10:00:00',
    ]);

    $payment = SubscriptionPayment::factory()->create([
        'subscription_id' => $subscription->id,
        'amount_minor' => 12_000,
        'currency' => 'USD',
    ]);

    $refund = app(CreateRefund::class)->execute(
        subscriptionPaymentId: $payment->id,
        refundedAt: Carbon::parse('2026-05-15 12:00:00'),
        idempotencyKey: 'refund-unused-months-1',
    );

    expect($refund->amount_minor)->toBe(7_000)
        ->and($refund->status)->toBe(RefundStatus::PENDING);
});

it('never refunds more than the original payment amount', function () {
    $plan = Plan::factory()->annual()->create([
        'price_minor' => 20_000,
        'duration_months' => 12,
    ]);

    $subscription = Subscription::factory()->create([
        'plan_id' => $plan->id,
        'amount_minor' => 20_000,
        'starts_at' => '2026-01-10 10:00:00',
        'ends_at' => '2027-01-10 10:00:00',
    ]);

    $payment = SubscriptionPayment::factory()->create([
        'subscription_id' => $subscription->id,
        'amount_minor' => 10_000,
        'currency' => 'USD',
    ]);

    $refund = app(CreateRefund::class)->execute(
        subscriptionPaymentId: $payment->id,
        refundedAt: Carbon::parse('2026-01-01 00:00:00'),
        idempotencyKey: 'refund-cannot-exceed-payment-1',
    );

    expect($refund->amount_minor)->toBe(10_000);
});

it('does not allow cumulative refunds to exceed the original payment amount', function () {
    $plan = Plan::factory()->annual()->create([
        'price_minor' => 12_000,
        'duration_months' => 12,
    ]);

    $subscription = Subscription::factory()->create([
        'plan_id' => $plan->id,
        'amount_minor' => 12_000,
        'starts_at' => '2026-01-10 10:00:00',
        'ends_at' => '2027-01-10 10:00:00',
    ]);

    $payment = SubscriptionPayment::factory()->create([
        'subscription_id' => $subscription->id,
        'amount_minor' => 12_000,
        'currency' => 'USD',
    ]);

    $service = app(CreateRefund::class);

    $firstRefund = $service->execute(
        subscriptionPaymentId: $payment->id,
        refundedAt: Carbon::parse('2026-05-15 12:00:00'),
        idempotencyKey: 'cumulative-refund-1',
    );

    $secondRefund = $service->execute(
        subscriptionPaymentId: $payment->id,
        refundedAt: Carbon::parse('2026-06-15 12:00:00'),
        idempotencyKey: 'cumulative-refund-2',
    );

    expect($firstRefund->amount_minor)->toBe(7_000)
        ->and($secondRefund->amount_minor)->toBe(5_000)
        ->and(
            (int) Refund::query()
                ->where('subscription_payment_id', $payment->id)
                ->sum('amount_minor')
        )->toBe(12_000);
});

it('does not over-refund when two refund requests run concurrently', function () {
    $plan = Plan::factory()->annual()->create([
        'price_minor' => 12_000,
        'duration_months' => 12,
    ]);

    $subscription = Subscription::factory()->create([
        'plan_id' => $plan->id,
        'amount_minor' => 12_000,
        'starts_at' => '2026-01-10 10:00:00',
        'ends_at' => '2027-01-10 10:00:00',
    ]);

    $payment = SubscriptionPayment::factory()->create([
        'subscription_id' => $subscription->id,
        'amount_minor' => 12_000,
        'currency' => 'USD',
    ]);

    $workerScript = base_path(
        'tests/Feature/Services/ConcurrentCreateRefundWorker.php'
    );

    file_put_contents($workerScript, <<<'PHP'
<?php

require __DIR__ . '/../../../vendor/autoload.php';

$app = require __DIR__ . '/../../../bootstrap/app.php';

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$paymentId = (int) $argv[1];
$refundDate = $argv[2];
$idempotencyKey = $argv[3];

$service = app(\App\Services\CreateRefund::class);

$refund = $service->execute(
    subscriptionPaymentId: $paymentId,
    refundedAt: \Carbon\Carbon::parse($refundDate),
    idempotencyKey: $idempotencyKey,
);

echo $refund->amount_minor;
PHP);

    $command = sprintf(
        '%s %s %d %s %s',
        PHP_BINARY,
        escapeshellarg($workerScript),
        $payment->id,
        escapeshellarg('2026-05-15 12:00:00'),
        escapeshellarg('concurrent-refund-' . uniqid()),
    );

    $processA = proc_open(
        $command,
        [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipesA,
    );

    $command = sprintf(
        '%s %s %d %s %s',
        PHP_BINARY,
        escapeshellarg($workerScript),
        $payment->id,
        escapeshellarg('2026-06-15 12:00:00'),
        escapeshellarg('concurrent-refund-' . uniqid()),
    );

    $processB = proc_open(
        $command,
        [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipesB,
    );

    $outputA = stream_get_contents($pipesA[1]);
    $errorA = stream_get_contents($pipesA[2]);

    $outputB = stream_get_contents($pipesB[1]);
    $errorB = stream_get_contents($pipesB[2]);

    $exitA = proc_close($processA);
    $exitB = proc_close($processB);

    unlink($workerScript);

    expect($exitA)->toBe(0, $errorA)
        ->and($exitB)->toBe(0, $errorB);

    $refunds = Refund::query()
        ->where('subscription_payment_id', $payment->id)
        ->get();

    expect($refunds)->toHaveCount(2)
        ->and(
            (int) $refunds->sum('amount_minor')
        )->toBe(12_000);
});