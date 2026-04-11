<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_wallet_pending_p2p_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_wallet_id')->constrained('whatsapp_wallets')->cascadeOnDelete();
            $table->string('recipient_phone_e164', 32);
            $table->decimal('amount', 14, 2);
            $table->string('status', 24)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->unsignedBigInteger('sender_debit_transaction_id')->nullable();
            $table->unsignedBigInteger('sender_refund_transaction_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['recipient_phone_e164', 'status', 'expires_at'], 'wa_p2p_pend_recv_stat_exp');
            $table->index(['status', 'expires_at'], 'wa_p2p_pend_stat_exp');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_wallet_pending_p2p_credits');
    }
};
