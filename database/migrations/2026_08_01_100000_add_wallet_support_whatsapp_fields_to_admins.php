<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            if (! Schema::hasColumn('admins', 'whatsapp_e164')) {
                $table->string('whatsapp_e164', 20)->nullable()->after('sidebar_menu_order');
            }
            if (! Schema::hasColumn('admins', 'notify_wallet_signup')) {
                $table->boolean('notify_wallet_signup')->default(false)->after('whatsapp_e164');
            }
            if (! Schema::hasColumn('admins', 'admin_page_permissions')) {
                $table->json('admin_page_permissions')->nullable()->after('notify_wallet_signup');
            }
        });

        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_wallets', 'wallet_signup_notified_at')) {
                $table->timestamp('wallet_signup_notified_at')->nullable()->after('referral_launch_notified_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            if (Schema::hasColumn('admins', 'admin_page_permissions')) {
                $table->dropColumn('admin_page_permissions');
            }
            if (Schema::hasColumn('admins', 'notify_wallet_signup')) {
                $table->dropColumn('notify_wallet_signup');
            }
            if (Schema::hasColumn('admins', 'whatsapp_e164')) {
                $table->dropColumn('whatsapp_e164');
            }
        });

        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_wallets', 'wallet_signup_notified_at')) {
                $table->dropColumn('wallet_signup_notified_at');
            }
        });
    }
};
