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
        if (! Schema::hasTable('payments')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Ensure payments.id is an auto-incrementing primary key.
        // Some environments lost both PRIMARY KEY and AUTO_INCREMENT on id.
        $indexes = DB::select('SHOW INDEX FROM payments');
        $hasPrimary = collect($indexes)->contains(function ($row) {
            return isset($row->Key_name) && $row->Key_name === 'PRIMARY';
        });

        if (! $hasPrimary) {
            DB::statement('ALTER TABLE payments ADD PRIMARY KEY (id)');
        }

        DB::statement('ALTER TABLE payments MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE payments MODIFY id BIGINT UNSIGNED NOT NULL');
    }
};

