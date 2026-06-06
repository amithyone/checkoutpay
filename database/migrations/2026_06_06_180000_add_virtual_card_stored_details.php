<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('virtual_card_requests')) {
            return;
        }

        Schema::table('virtual_card_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('virtual_card_requests', 'card_details_payload')) {
                $table->text('card_details_payload')->nullable()->after('response_payload');
            }
            if (! Schema::hasColumn('virtual_card_requests', 'card_balance_usd')) {
                $table->decimal('card_balance_usd', 12, 2)->nullable()->after('card_details_payload');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('virtual_card_requests')) {
            return;
        }

        Schema::table('virtual_card_requests', function (Blueprint $table) {
            if (Schema::hasColumn('virtual_card_requests', 'card_balance_usd')) {
                $table->dropColumn('card_balance_usd');
            }
            if (Schema::hasColumn('virtual_card_requests', 'card_details_payload')) {
                $table->dropColumn('card_details_payload');
            }
        });
    }
};
