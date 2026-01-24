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
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('telegram_bot_token')->nullable()->after('notifications_security_enabled');
            $table->string('telegram_chat_id', 255)->nullable()->after('telegram_bot_token');
            $table->boolean('telegram_withdrawal_enabled')->default(false)->after('telegram_chat_id');
            $table->boolean('telegram_security_enabled')->default(false)->after('telegram_withdrawal_enabled');
            $table->boolean('telegram_payment_enabled')->default(false)->after('telegram_security_enabled');
            $table->boolean('telegram_login_enabled')->default(false)->after('telegram_payment_enabled');
            $table->boolean('telegram_admin_login_enabled')->default(false)->after('telegram_login_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'telegram_bot_token',
                'telegram_chat_id',
                'telegram_withdrawal_enabled',
                'telegram_security_enabled',
                'telegram_payment_enabled',
                'telegram_login_enabled',
                'telegram_admin_login_enabled',
            ]);
        });
    }
};
