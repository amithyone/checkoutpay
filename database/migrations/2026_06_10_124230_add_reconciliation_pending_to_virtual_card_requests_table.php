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
        Schema::table('virtual_card_requests', function (Blueprint $table) {
            $table->boolean('reconciliation_pending')->default(false)->after('card_balance_usd');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('virtual_card_requests', function (Blueprint $table) {
            $table->dropColumn('reconciliation_pending');
        });
    }
};
