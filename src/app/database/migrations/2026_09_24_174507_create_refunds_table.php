<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
        
            $table->foreignId('subscription_payment_id')
                ->constrained('subscription_payments')
                ->restrictOnDelete();
        
            $table->unsignedBigInteger('amount_minor');
        
            $table->char('currency', 3);
        
            $table->string('provider_reference', 150)->nullable();
        
            $table->string('idempotency_key', 100);
        
            $table->string('status', 20);
        
            $table->text('failure_reason')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
        
            $table->unique('idempotency_key');
        
            $table->index('subscription_payment_id');
        
            $table->index('provider_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};