<?php

namespace App\Services;

use App\Contracts\RefundProvider;
use App\Contracts\RefundProviderResult;
use App\Models\Refund;

class MockRefundProvider implements RefundProvider
{
    public int $calls = 0;
    public bool $shouldTimeout = false;

    public function __construct(
        private string $refundStatus = 'succeeded',
        private ?string $failureReason = null,
    ) {}

    public function refund(Refund $refund): RefundProviderResult
    {
        $this->calls++;
    
        if ($this->shouldTimeout) {
            throw new \RuntimeException('Provider timeout');
        }
    
        return new RefundProviderResult(
            status: $this->refundStatus,
            providerReference: $this->refundStatus === 'failed'
                ? null
                : 'mock-refund-' . $refund->id,
            failureReason: $this->failureReason,
        );
    }

    public function checkStatus(Refund $refund): RefundProviderResult
    {
        return new RefundProviderResult(
            status: $this->refundStatus,
            providerReference: $this->refundStatus === 'succeeded'
                ? 'mock-refund-' . $refund->id
                : $refund->provider_reference,
            failureReason: $this->failureReason,
        );
    }
}