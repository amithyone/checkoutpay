<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_wallet_money_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('requester_wallet_id')->constrained('whatsapp_wallets')->cascadeOnDelete();
            $table->string('requester_phone_e164', 32);
            $table->string('payer_phone_e164', 32);
            $table->unsignedBigInteger('payer_wallet_id')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 8)->default('NGN');
            $table->string('note', 140)->nullable();
            $table->string('status', 24)->default('pending');
            $table->string('channel', 24)->default('consumer_api');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->unsignedBigInteger('p2p_debit_transaction_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['payer_phone_e164', 'status'], 'wa_money_req_payer_stat');
            $table->index(['requester_wallet_id', 'status'], 'wa_money_req_req_stat');
            $table->index(['status', 'expires_at'], 'wa_money_req_stat_exp');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_wallet_money_requests');
    }
};
