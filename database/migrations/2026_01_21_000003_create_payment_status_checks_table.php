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
        Schema::create('payment_status_checks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_id')->index();
            $table->string('transaction_id')->index();
            $table->unsignedBigInteger('business_id')->nullable()->index();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->enum('payment_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade');
            $table->foreign('business_id')->references('id')->on('businesses')->onDelete('set null');
            
            // Indexes for common queries
            $table->index(['transaction_id', 'created_at']);
            $table->index(['business_id', 'created_at']);
            $table->index(['payment_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_status_checks');
    }
};
