<?php

namespace App\Models;

use App\Enums\PlanType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'price_minor',
        'currency',
        'duration_months',
    ];

    protected function casts(): array
    {
        return [
            'type' => PlanType::class,
            'price_minor' => 'integer',
            'duration_months' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
