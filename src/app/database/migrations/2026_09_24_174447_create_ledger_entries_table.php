<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->restrictOnDelete();

            $table->foreignId('instructor_id')->nullable()->constrained('instructors')->restrictOnDelete();

            $table->string('type', 30);

            $table->unsignedBigInteger('amount_minor');

            $table->char('currency', 3);

            $table->timestamp('occurred_at');

            $table->string('source_type', 100);
            $table->unsignedBigInteger('source_id');

            $table->string('idempotency_key', 150);

            $table->timestamp('created_at')->useCurrent();

            $table->unique('idempotency_key');

            $table->index(
                ['instructor_id', 'occurred_at'],
                'ledger_instructor_occurred_index'
            );

            $table->index(
                ['subscription_id', 'occurred_at'],
                'ledger_subscription_occurred_index'
            );

            $table->index(
                ['source_type', 'source_id'],
                'ledger_source_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};