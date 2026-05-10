<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Repair: 2026_02_02_162513 may have no-op'd when `events` was missing but still recorded as migrated.
     */
    public function up(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        if (Schema::hasColumn('events', 'view_count')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            $table->unsignedBigInteger('view_count')->default(0)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('events') || ! Schema::hasColumn('events', 'view_count')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('view_count');
        });
    }
};
