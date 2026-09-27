<?php

namespace App\Models;

use App\Enums\SubscriptionPaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SubscriptionPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'provider',
        'provider_reference',
        'amount_minor',
        'currency',
        'status',
        'idempotency_key',
        'failure_reason',
        'paid_at',
        'processing_started_at'
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'status' => SubscriptionPaymentStatus::class,
            'paid_at' => 'datetime',
            'processing_started_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }
}