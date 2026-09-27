<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_id')->constrained('subscriptions')->restrictOnDelete();

            $table->string('provider', 50)->nullable();
            $table->string('provider_reference', 150)->nullable();

            $table->unsignedBigInteger('amount_minor');

            $table->char('currency', 3);

            $table->string('status', 20);
            $table->text('failure_reason')->nullable();
            $table->string('idempotency_key', 100);

            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['provider', 'provider_reference'],
                'payments_provider_reference_unique'
            );

            $table->unique('idempotency_key');

            $table->index(['subscription_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};