<?php

namespace Tests\Fakes;

use App\Contracts\PayoutProvider;
use App\Contracts\PayoutResult;
use App\Models\Payout;

class FakePayoutProvider implements PayoutProvider
{
    public int $calls = 0;

    public int $statusChecks = 0;

    public function __construct(
        private PayoutResult $result,
        private ?PayoutResult $statusResult = null,
    ) {}

    public function send(Payout $payout): PayoutResult
    {
        $this->calls++;

        return $this->result;
    }

    public function checkStatus(Payout $payout): PayoutResult
    {
        $this->statusChecks++;

        return $this->statusResult ?? $this->result;
    }
}