<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();

            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->string('idempotency_key', 150)->unique();
            $table->unsignedBigInteger('amount_minor');

            $table->char('currency', 3);

            $table->string('status', 20);

            $table->timestamp('starts_at');
            $table->timestamp('ends_at');

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};