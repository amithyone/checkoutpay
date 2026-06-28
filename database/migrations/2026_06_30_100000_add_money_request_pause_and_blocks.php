<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_wallets', 'money_request_balance_hint_enabled')) {
                $table->boolean('money_request_balance_hint_enabled')->default(true);
            }
            if (! Schema::hasColumn('whatsapp_wallets', 'money_request_paused_until')) {
                $table->timestamp('money_request_paused_until')->nullable();
            }
        });

        Schema::create('whatsapp_wallet_money_request_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_wallet_id')->constrained('whatsapp_wallets')->cascadeOnDelete();
            $table->string('blocked_phone_e164', 32);
            $table->foreignId('blocked_wallet_id')->nullable()->constrained('whatsapp_wallets')->nullOnDelete();
            $table->string('blocked_display_name', 128)->nullable();
            $table->timestamps();

            $table->unique(['whatsapp_wallet_id', 'blocked_phone_e164'], 'wallet_money_req_block_unique');
            $table->index('blocked_phone_e164');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_wallet_money_request_blocks');

        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_wallets', 'money_request_paused_until')) {
                $table->dropColumn('money_request_paused_until');
            }
        });
    }
};
