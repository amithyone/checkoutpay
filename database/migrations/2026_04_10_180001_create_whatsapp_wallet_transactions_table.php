<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_wallet_id')->constrained('whatsapp_wallets')->cascadeOnDelete();
            $table->string('type', 32);
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_after', 14, 2)->nullable();
            $table->string('counterparty_phone_e164', 32)->nullable();
            $table->string('counterparty_account_number', 32)->nullable();
            $table->string('counterparty_bank_code', 32)->nullable();
            $table->string('counterparty_account_name')->nullable();
            $table->string('external_reference')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['whatsapp_wallet_id', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_wallet_transactions');
    }
};
