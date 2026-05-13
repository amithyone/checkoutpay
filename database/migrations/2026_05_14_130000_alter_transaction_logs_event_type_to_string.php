<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen event_type so new log types (e.g. developer program) do not hit SQLite CHECK / MySQL ENUM limits.
     */
    public function up(): void
    {
        if (! Schema::hasTable('transaction_logs')) {
            return;
        }

        Schema::table('transaction_logs', function (Blueprint $table) {
            $table->string('event_type', 100)->change();
        });
    }

    public function down(): void
    {
        // Non-reversible safely across SQLite/MySQL; keep as string in production.
    }
};
