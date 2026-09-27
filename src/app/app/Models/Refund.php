<?php

namespace App\Models;

use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Refund extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_payment_id',
        'amount_minor',
        'currency',
        'provider_reference',
        'idempotency_key',
        'status',
        'failure_reason',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RefundStatus::class,
            'amount_minor' => 'integer',
            'refunded_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(
            SubscriptionPayment::class,
            'subscription_payment_id'
        );
    }
}