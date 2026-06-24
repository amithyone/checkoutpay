<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite' || ! Schema::hasTable('processed_emails')) {
            return;
        }

        DB::statement(
            'ALTER TABLE processed_emails CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        // No-op: reverting charset risks data loss for 4-byte characters.
    }
};
