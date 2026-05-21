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
            if (! Schema::hasColumn('whatsapp_wallets', 'support_whatsapp_welcome_sent_at')) {
                $table->timestamp('support_whatsapp_welcome_sent_at')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('whatsapp_wallets')) {
            return;
        }

        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_wallets', 'support_whatsapp_welcome_sent_at')) {
                $table->dropColumn('support_whatsapp_welcome_sent_at');
            }
        });
    }
};
