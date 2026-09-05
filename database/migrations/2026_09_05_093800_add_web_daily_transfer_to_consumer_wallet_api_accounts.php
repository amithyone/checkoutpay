<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('consumer_wallet_api_accounts')) {
            return;
        }

        Schema::table('consumer_wallet_api_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('consumer_wallet_api_accounts', 'web_daily_transfer_total')) {
                $table->decimal('web_daily_transfer_total', 14, 2)->default(0)->after('pin_reset_required');
            }
            if (! Schema::hasColumn('consumer_wallet_api_accounts', 'web_daily_transfer_for_date')) {
                $table->date('web_daily_transfer_for_date')->nullable()->after('web_daily_transfer_total');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('consumer_wallet_api_accounts')) {
            return;
        }

        Schema::table('consumer_wallet_api_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('consumer_wallet_api_accounts', 'web_daily_transfer_for_date')) {
                $table->dropColumn('web_daily_transfer_for_date');
            }
            if (Schema::hasColumn('consumer_wallet_api_accounts', 'web_daily_transfer_total')) {
                $table->dropColumn('web_daily_transfer_total');
            }
        });
    }
};
