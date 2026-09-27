<?php

namespace Tests\Feature\Services;

use App\Contracts\PaymentResult;
use App\Enums\SubscriptionPaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\CreateSubscription;
use Tests\Fakes\FakePaymentProvider;
use Tests\TestCase;
use App\Enums\PlanType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

it('activates subscription when payment succeeds', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->monthly()->create();

    $provider = new FakePaymentProvider(
        new PaymentResult(
            status: SubscriptionPaymentStatus::PAID,
            providerReference: 'provider-123',
        )
    );

    $service = new CreateSubscription($provider);

    $subscription = $service->execute(
        user: $user,
        plan: $plan,
        idempotencyKey: 'subscription-request-123',
    );

    expect($subscription->status)
        ->toBe(SubscriptionStatus::ACTIVE);

    $payment = $subscription->payments()->first();

    expect($payment)->not->toBeNull()
        ->and($payment->status)
        ->toBe(SubscriptionPaymentStatus::PAID)
        ->and($payment->provider_reference)
        ->toBe('provider-123')
        ->and($payment->paid_at)
        ->not->toBeNull();

    expect($provider->calls)->toBe(1);
});

it('test_it_keeps_subscription_pending_when_payment_fails', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->monthly()->create();

    $provider = new FakePaymentProvider(
        new PaymentResult(
            status: SubscriptionPaymentStatus::FAILED,
            message: 'Card declined',
        )
    );

    $service = new CreateSubscription($provider);

    $subscription = $service->execute(
        user: $user,
        plan: $plan,
        idempotencyKey: 'subscription-request-456',
    );

    $this->assertSame(
        SubscriptionStatus::PENDING,
        $subscription->status
    );

    $payment = $subscription->payments()->first();

    $this->assertSame(
        SubscriptionPaymentStatus::FAILED,
        $payment->status
    );

    $this->assertSame(
        'Card declined',
        $payment->failure_reason
    );

    $this->assertNull($payment->paid_at);

    $this->assertSame(1, $provider->calls);
});

it('test_it_keeps_subscription_pending_when_payment_result_is_unknown', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->monthly()->create();

    $provider = new FakePaymentProvider(
        new PaymentResult(
            status: SubscriptionPaymentStatus::UNKNOWN,
            providerReference: 'provider-unknown-123',
            message: 'Provider timeout',
        )
    );

    $service = new CreateSubscription($provider);

    $subscription = $service->execute(
        user: $user,
        plan: $plan,
        idempotencyKey: 'subscription-request-789',
    );

    $this->assertSame(
        SubscriptionStatus::PENDING,
        $subscription->status
    );

    $payment = $subscription->payments()->first();

    $this->assertSame(
        SubscriptionPaymentStatus::UNKNOWN,
        $payment->status
    );

    $this->assertSame(
        'provider-unknown-123',
        $payment->provider_reference
    );

    $this->assertSame(
        'Provider timeout',
        $payment->failure_reason
    );

    $this->assertNull($payment->paid_at);

    $this->assertSame(1, $provider->calls);
});

it('returns the same subscription for the same idempotency key', function () {
    $user = User::factory()->create();

    $plan = Plan::factory()->monthly()->create();


    $provider = new FakePaymentProvider(
        new PaymentResult(
            status: SubscriptionPaymentStatus::PAID,
            providerReference: 'provider-123',
        )
    );

    $service = new CreateSubscription($provider);

    $first = $service->execute(
        user: $user,
        plan: $plan,
        idempotencyKey: 'same-request-key',
    );

    $second = $service->execute(
        user: $user,
        plan: $plan,
        idempotencyKey: 'same-request-key',
    );

    expect($second->id)->toBe($first->id);

    expect(Subscription::query()->count())
        ->toBe(1);

    expect(SubscriptionPayment::query()->count())
        ->toBe(1);

    expect($provider->calls)
        ->toBe(1);
});
