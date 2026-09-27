<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructors', function (Blueprint $table) {
            $table->id();

            $table->string('name', 150);
            $table->string('email')->unique();

            $table->decimal('payout_percentage', 5, 2)->default(0);

            $table->string('status', 20)->default('active');

            $table->timestamps();

            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructors');
    }
};