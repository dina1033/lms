<?php

namespace App\Enums;

enum SubscriptionPaymentStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case FAILED = 'failed';
    case UNKNOWN = 'unknown';
    case PROCESSING = 'processing';
}