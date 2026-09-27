<?php

namespace App\Services;

use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class RevenueAllocationService
{
    public function allocate(Subscription $subscription): void
    {
        DB::transaction(function () use ($subscription) {
            $subscription = Subscription::query()
                ->whereKey($subscription->id)
                ->lockForUpdate()
                ->firstOrFail();
    
            if ($subscription->revenueAllocations()->exists()) {
                return;
            }
    
            $instructors = $subscription->instructors()
                ->orderBy('instructors.id')
                ->get();
    
            if ($instructors->isEmpty()) {
                return;
            }
    
            $platformPercentage = (float) config(
                'services.platform.revenue_percentage'
            );
    
            $platformAmount = (int) round(
                $subscription->amount_minor * ($platformPercentage / 100)
            );
    
            $instructorPool = $subscription->amount_minor - $platformAmount;
    
            $instructorCount = $instructors->count();
    
            $baseAmount = intdiv(
                $instructorPool,
                $instructorCount
            );
    
            $remainder = $instructorPool % $instructorCount;
    
            foreach ($instructors as $index => $instructor) {
                $amount = $baseAmount;
    
                if ($index < $remainder) {
                    $amount++;
                }
    
                $percentage = ($amount / $instructorPool) * 100;
    
                $subscription->revenueAllocations()->create([
                    'instructor_id' => $instructor->id,
                    'percentage' => $percentage,
                    'amount_minor' => $amount,
                    'currency' => $subscription->currency,
                ]);
            }
        });
    }
}