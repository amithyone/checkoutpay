<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            $table->boolean('notify_card_created_email')->default(true)->after('transfer_email_otp_enabled');
            $table->boolean('notify_card_created_whatsapp')->default(true)->after('notify_card_created_email');
            $table->boolean('notify_card_transaction_email')->default(true)->after('notify_card_created_whatsapp');
            $table->boolean('notify_card_transaction_whatsapp')->default(true)->after('notify_card_transaction_email');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            $table->dropColumn([
                'notify_card_created_email',
                'notify_card_created_whatsapp',
                'notify_card_transaction_email',
                'notify_card_transaction_whatsapp',
            ]);
        });
    }
};
