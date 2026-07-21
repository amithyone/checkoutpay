<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('virtual_card_requests')
            || Schema::hasColumn('virtual_card_requests', 'auto_freeze_on_decline')) {
            return;
        }

        Schema::table('virtual_card_requests', function (Blueprint $table) {
            if (Schema::hasColumn('virtual_card_requests', 'is_frozen')) {
                $table->boolean('auto_freeze_on_decline')->default(true)->after('is_frozen');
            } else {
                $table->boolean('auto_freeze_on_decline')->default(true);
            }
        });
    }

    public function down(): void
    {
        Schema::table('virtual_card_requests', function (Blueprint $table) {
            $table->dropColumn('auto_freeze_on_decline');
        });
    }
};
