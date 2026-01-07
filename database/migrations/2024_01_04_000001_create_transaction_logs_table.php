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
        Schema::create('transaction_logs', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id')->index();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->unsignedBigInteger('business_id')->nullable();
            $table->enum('event_type', [
                'payment_requested',
                'account_assigned',
                'email_received',
                'payment_matched',
                'payment_approved',
                'payment_rejected',
                'payment_expired',
                'webhook_sent',
                'webhook_failed',
                'withdrawal_requested',
                'withdrawal_approved',
                'withdrawal_rejected',
                'withdrawal_processed',
            ]);
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Store additional data
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index('transaction_id');
            $table->index('event_type');
            $table->index('created_at');
            $table->index(['transaction_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_logs');
    }
};
