<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscription_instructors', function (Blueprint $table) {
            $table->id();
        
            $table->foreignId('subscription_id')->constrained('subscriptions')->restrictOnDelete();
        
            $table->foreignId('instructor_id')->constrained('instructors')->restrictOnDelete();
        
            $table->timestamps();
        
            $table->unique(
                ['subscription_id', 'instructor_id'],
                'subscription_instructor_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_instructors');
    }
};
