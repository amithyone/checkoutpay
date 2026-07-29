<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_wallets')) {
            return;
        }

        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_wallets', 'referral_launch_notified_at')) {
                $table->timestamp('referral_launch_notified_at')->nullable()->after('support_whatsapp_welcome_sent_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('whatsapp_wallets')) {
            return;
        }

        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_wallets', 'referral_launch_notified_at')) {
                $table->dropColumn('referral_launch_notified_at');
            }
        });
    }
};
