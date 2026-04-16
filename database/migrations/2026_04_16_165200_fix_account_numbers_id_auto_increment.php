<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('account_numbers')) {
            return;
        }

        $indexes = DB::select('SHOW INDEX FROM account_numbers');
        $hasPrimary = collect($indexes)->contains(function ($row) {
            return isset($row->Key_name) && $row->Key_name === 'PRIMARY';
        });

        if (! $hasPrimary) {
            DB::statement('ALTER TABLE account_numbers ADD PRIMARY KEY (id)');
        }

        DB::statement('ALTER TABLE account_numbers MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('account_numbers')) {
            return;
        }

        DB::statement('ALTER TABLE account_numbers MODIFY id BIGINT UNSIGNED NOT NULL');
    }
};

