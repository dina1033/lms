<?php

namespace App\Enums;

enum LedgerEntryType: string
{
    case REVENUE = 'subscription_revenue';
    case REFUND = 'refund';
    case ADJUSTMENT = 'adjustment';
}