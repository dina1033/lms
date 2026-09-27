<?php

namespace Tests\Fakes;

use App\Contracts\PaymentProvider;
use App\Contracts\PaymentResult;
use App\Models\SubscriptionPayment;

class FakePaymentProvider implements PaymentProvider
{
    public int $calls = 0;

    public function __construct(
        private PaymentResult $result,
    ) {}

    public function charge(
        SubscriptionPayment $payment,
    ): PaymentResult {
        $this->calls++;

        return $this->result;
    }
}