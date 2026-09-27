<?php

namespace App\Contracts;

final readonly class PayoutResult
{
    public function __construct(
        public string $status,
        public ?string $providerReference = null,
        public ?string $message = null,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isUnknown(): bool
    {
        return $this->status === 'unknown';
    }
}