<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_wallet_pending_topups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_wallet_id')->constrained('whatsapp_wallets')->cascadeOnDelete();
            $table->string('account_number', 32);
            $table->string('account_name')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_code', 32)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('fulfilled_at')->nullable();
            $table->decimal('amount_reported', 14, 2)->nullable();
            $table->decimal('amount_credited', 14, 2)->nullable();
            $table->string('mavon_reference')->nullable();
            $table->timestamps();

            $table->index(['account_number', 'fulfilled_at', 'expires_at'], 'ww_topup_acct_fulfill_exp_idx');
            $table->index(['whatsapp_wallet_id', 'fulfilled_at'], 'ww_topup_wallet_fulfill_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_wallet_pending_topups');
    }
};
