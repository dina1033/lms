<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
        
            $table->foreignId('instructor_id')->constrained('instructors')->restrictOnDelete();
        
            $table->timestamp('period_start');
        
            $table->timestamp('period_end');
        
            $table->unsignedBigInteger('amount_minor');
        
            $table->char('currency', 3);
        
            $table->string('status', 20);
        
            $table->string('provider', 50)->nullable();
        
            $table->string('provider_payout_reference', 150)->nullable();
        
            $table->string('idempotency_key', 150)->unique();

            $table->string('provider_reference')->nullable()->index();

            $table->timestamp('processing_started_at')->nullable();

            $table->text('failure_reason')->nullable();
            
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
        
            $table->timestamps();
        
            $table->unique(
                ['instructor_id', 'period_start', 'period_end'],
                'instructor_payout_period_unique'
            );
        
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};