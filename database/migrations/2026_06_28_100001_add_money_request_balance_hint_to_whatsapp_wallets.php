<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            $table->boolean('money_request_balance_hint_enabled')->default(true)->after('transfer_email_otp_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            $table->dropColumn('money_request_balance_hint_enabled');
        });
    }
};
