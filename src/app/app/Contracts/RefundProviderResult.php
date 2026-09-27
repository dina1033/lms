<?php

namespace App\Contracts;

class RefundProviderResult
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $providerReference = null,
        public readonly ?string $failureReason = null,
    ) {}
}