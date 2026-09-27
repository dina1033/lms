<?php

namespace App\Contracts;

use App\Models\Payout;

interface PayoutProvider
{
    public function send(Payout $payout): PayoutResult;

    public function checkStatus(Payout $payout): PayoutResult;
}