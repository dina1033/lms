<?php

namespace App\Contracts;

use App\Enums\SubscriptionPaymentStatus;

class PaymentResult
{
    public function __construct(
        public readonly SubscriptionPaymentStatus $status,
        public readonly ?string $providerReference = null,
        public readonly ?string $message = null,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->status === SubscriptionPaymentStatus::PAID;
    }

    public function isFailed(): bool
    {
        return $this->status === SubscriptionPaymentStatus::FAILED;
    }

    public function isUnknown(): bool
    {
        return $this->status === SubscriptionPaymentStatus::UNKNOWN;
    }
}