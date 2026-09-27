<?php

namespace App\Contracts;

use App\Models\SubscriptionPayment;

interface PaymentProvider
{
    public function charge(
        SubscriptionPayment $payment,
    ): PaymentResult;
}