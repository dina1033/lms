<?php

namespace App\Contracts;

use App\Models\Refund;

interface RefundProvider
{
    public function refund(Refund $refund): RefundProviderResult;

    public function checkStatus(Refund $refund): RefundProviderResult;
}