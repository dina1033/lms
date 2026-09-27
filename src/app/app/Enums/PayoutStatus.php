<?php

namespace App\Enums;

enum PayoutStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case PAID = 'paid';
    case FAILED = 'failed';
    case UNKNOWN = 'unknown';
}