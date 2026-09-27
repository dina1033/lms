<?php

namespace App\Services;

use App\Contracts\PayoutProvider;
use App\Contracts\PayoutResult;
use App\Models\Payout;

class MockPayoutProvider implements PayoutProvider
{
    public function send(Payout $payout): PayoutResult
    {
        return new PayoutResult(
            status: 'success',
            providerReference: 'mock-provider-' . $payout->id,
        );
    }

    public function checkStatus(Payout $payout): PayoutResult
    {
        return new PayoutResult(
            status: 'success',
            providerReference: $payout->provider_payout_reference
                ?? 'mock-provider-' . $payout->id,
        );
    }
}