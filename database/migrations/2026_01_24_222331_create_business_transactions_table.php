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
        Schema::create('business_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->onDelete('cascade');
            $table->foreignId('payment_id')->nullable()->constrained('payments')->onDelete('set null');
            $table->decimal('amount', 15, 2);
            $table->enum('type', ['payment', 'withdrawal'])->default('payment');
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])->default('completed');
            $table->dateTime('transaction_date');
            $table->timestamps();

            $table->index(['business_id', 'transaction_date']);
            $table->index('payment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_transactions');
    }
};
