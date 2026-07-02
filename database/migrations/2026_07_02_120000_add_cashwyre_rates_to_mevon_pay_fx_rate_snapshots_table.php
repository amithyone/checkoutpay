<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mevon_pay_fx_rate_snapshots', function (Blueprint $table) {
            $table->decimal('cashwyre_mid', 12, 4)->nullable()->after('buy_rate');
            $table->decimal('cashwyre_sell_rate', 12, 4)->nullable()->after('cashwyre_mid');
            $table->decimal('cashwyre_buy_rate', 12, 4)->nullable()->after('cashwyre_sell_rate');
        });
    }

    public function down(): void
    {
        Schema::table('mevon_pay_fx_rate_snapshots', function (Blueprint $table) {
            $table->dropColumn(['cashwyre_mid', 'cashwyre_sell_rate', 'cashwyre_buy_rate']);
        });
    }
};
