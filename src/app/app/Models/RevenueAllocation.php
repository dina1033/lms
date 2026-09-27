<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RevenueAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'instructor_id',
        'percentage',
        'amount_minor',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:4',
            'amount_minor' => 'integer',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }
}