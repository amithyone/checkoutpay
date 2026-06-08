<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumer_wallet_api_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('consumer_wallet_api_accounts', 'last_app_active_at')) {
                $table->timestamp('last_app_active_at')->nullable()->after('fcm_token_updated_at');
            }
        });

        if (! Schema::hasTable('whatsapp_wallet_inactive_reminders')) {
            Schema::create('whatsapp_wallet_inactive_reminders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('whatsapp_wallet_id')->constrained('whatsapp_wallets')->cascadeOnDelete();
                $table->date('reminder_on');
                $table->string('slot', 16);
                $table->boolean('push_sent')->default(false);
                $table->boolean('whatsapp_sent')->default(false);
                $table->timestamps();

                $table->unique(['whatsapp_wallet_id', 'reminder_on', 'slot'], 'wa_wallet_inactive_reminder_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_wallet_inactive_reminders');

        Schema::table('consumer_wallet_api_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('consumer_wallet_api_accounts', 'last_app_active_at')) {
                $table->dropColumn('last_app_active_at');
            }
        });
    }
};
