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
            if (! Schema::hasColumn('virtual_card_requests', 'is_frozen')) {
                $table->boolean('is_frozen')->default(false)->after('card_external_id');
            }
            if (! Schema::hasColumn('virtual_card_requests', 'last_operation_at')) {
                $table->timestamp('last_operation_at')->nullable()->after('is_frozen');
            }
            if (! Schema::hasColumn('virtual_card_requests', 'last_operation_payload')) {
                $table->json('last_operation_payload')->nullable()->after('last_operation_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('virtual_card_requests')) {
            return;
        }

        Schema::table('virtual_card_requests', function (Blueprint $table) {
            foreach (['is_frozen', 'last_operation_at', 'last_operation_payload'] as $col) {
                if (Schema::hasColumn('virtual_card_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
