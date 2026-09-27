<?php

namespace App\Enums;

enum PlanType: string
{
    case MONTHLY = 'monthly';
    case THREE_MONTH = 'three_month';
    case ANNUAL = 'annual';
}