<?php

namespace Tests\Unit\Contracts;

use App\Contracts\PaymentResult;
use App\Enums\SubscriptionPaymentStatus;
use App\Models\SubscriptionPayment;
use Tests\Fakes\FakePaymentProvider;
use Tests\TestCase;

class FakePaymentProviderTest extends TestCase
{
    public function test_it_returns_the_configured_payment_result(): void
    {
        $result = new PaymentResult(
            status: SubscriptionPaymentStatus::PAID,
            providerReference: 'payment-123',
        );

        $provider = new FakePaymentProvider($result);

        $payment = new SubscriptionPayment();

        $response = $provider->charge($payment);

        $this->assertTrue($response->isSuccessful());
        $this->assertSame(
            'payment-123',
            $response->providerReference
        );

        $this->assertSame(1, $provider->calls);
    }
}