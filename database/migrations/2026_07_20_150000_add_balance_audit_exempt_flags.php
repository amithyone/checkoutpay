<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('businesses') && ! Schema::hasColumn('businesses', 'balance_audit_exempt')) {
            Schema::table('businesses', function (Blueprint $table) {
                $table->boolean('balance_audit_exempt')->default(false)->after('charge_exempt');
            });
        }

        if (Schema::hasTable('whatsapp_wallets') && ! Schema::hasColumn('whatsapp_wallets', 'balance_audit_exempt')) {
            Schema::table('whatsapp_wallets', function (Blueprint $table) {
                $table->boolean('balance_audit_exempt')->default(false)->after('status');
            });
        }

        if (Schema::hasTable('renters') && ! Schema::hasColumn('renters', 'balance_audit_exempt')) {
            Schema::table('renters', function (Blueprint $table) {
                $table->boolean('balance_audit_exempt')->default(false)->after('wallet_balance');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('businesses') && Schema::hasColumn('businesses', 'balance_audit_exempt')) {
            Schema::table('businesses', function (Blueprint $table) {
                $table->dropColumn('balance_audit_exempt');
            });
        }
        if (Schema::hasTable('whatsapp_wallets') && Schema::hasColumn('whatsapp_wallets', 'balance_audit_exempt')) {
            Schema::table('whatsapp_wallets', function (Blueprint $table) {
                $table->dropColumn('balance_audit_exempt');
            });
        }
        if (Schema::hasTable('renters') && Schema::hasColumn('renters', 'balance_audit_exempt')) {
            Schema::table('renters', function (Blueprint $table) {
                $table->dropColumn('balance_audit_exempt');
            });
        }
    }
};
