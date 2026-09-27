<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenue_allocations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_id')->constrained('subscriptions')->restrictOnDelete();

            $table->foreignId('instructor_id')->constrained('instructors')->restrictOnDelete();

            $table->decimal('percentage', 7, 4);

            $table->unsignedBigInteger('amount_minor');

            $table->char('currency', 3);

            $table->timestamps();

            $table->unique(
                ['subscription_id', 'instructor_id'],
                'subscription_instructor_unique'
            );

            $table->index(['instructor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_allocations');
    }
};