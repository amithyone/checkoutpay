<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_wallets', 'flexible_savings_balance')) {
                $table->decimal('flexible_savings_balance', 14, 2)->default(0)->after('savings_balance');
            }
        });

        Schema::table('wallet_savings_locks', function (Blueprint $table) {
            if (! Schema::hasColumn('wallet_savings_locks', 'lock_type')) {
                $table->string('lock_type', 20)->default('locked')->after('source');
            }
            if (! Schema::hasColumn('wallet_savings_locks', 'ledger_scope')) {
                $table->string('ledger_scope', 20)->default('personal')->after('lock_type');
            }
        });

        if (Schema::hasColumn('wallet_savings_locks', 'matures_at')) {
            DB::statement('ALTER TABLE wallet_savings_locks MODIFY matures_at TIMESTAMP NULL');
        }
    }

    public function down(): void
    {
        Schema::table('wallet_savings_locks', function (Blueprint $table) {
            if (Schema::hasColumn('wallet_savings_locks', 'ledger_scope')) {
                $table->dropColumn('ledger_scope');
            }
            if (Schema::hasColumn('wallet_savings_locks', 'lock_type')) {
                $table->dropColumn('lock_type');
            }
        });

        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_wallets', 'flexible_savings_balance')) {
                $table->dropColumn('flexible_savings_balance');
            }
        });
    }
};
