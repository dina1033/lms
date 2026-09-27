<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payout_id')->constrained('payouts')->restrictOnDelete();

            $table->foreignId('ledger_entry_id')->constrained('ledger_entries')->restrictOnDelete();

            $table->unsignedBigInteger('amount_minor');

            $table->timestamp('created_at')->useCurrent();

            $table->unique('ledger_entry_id');
            $table->index('payout_id');

            $table->index('ledger_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_items');
    }
};