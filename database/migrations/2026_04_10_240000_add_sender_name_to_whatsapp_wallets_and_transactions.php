<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            $table->string('sender_name', 160)->nullable()->after('pin_locked_until');
        });

        Schema::table('whatsapp_wallet_transactions', function (Blueprint $table) {
            $table->string('sender_name', 160)->nullable()->after('whatsapp_wallet_id');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            $table->dropColumn('sender_name');
        });

        Schema::table('whatsapp_wallet_transactions', function (Blueprint $table) {
            $table->dropColumn('sender_name');
        });
    }
};
