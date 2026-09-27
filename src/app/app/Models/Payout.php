<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payout extends Model
{
    use HasFactory;
    protected $fillable = [
        'instructor_id',
        'period_start',
        'period_end',
        'amount_minor',
        'currency',
        'status',
        'provider',
        'provider_payout_reference',
        'idempotency_key',
        'processing_started_at',
        'processed_at',
        'paid_at',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => PayoutStatus::class,
            'amount_minor' => 'integer',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'processing_started_at' => 'datetime',
            'processed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayoutItem::class);
    }
}