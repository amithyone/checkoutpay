<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Laravel queue inserts into `jobs` without specifying `id`; MySQL requires
     * `id` to be AUTO_INCREMENT. Some databases had `jobs` created without it
     * (e.g. table existed before the standard migration ran).
     */
    public function up(): void
    {
        if (! Schema::hasTable('jobs')) {
            return;
        }

        $connection = Schema::getConnection();
        if ($connection->getDriverName() !== 'mysql') {
            return;
        }

        $database = $connection->getDatabaseName();
        $row = DB::selectOne(
            'SELECT COLUMN_TYPE, EXTRA
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?',
            [$database, 'jobs', 'id']
        );

        if (! $row) {
            return;
        }

        $extra = strtolower((string) ($row->EXTRA ?? ''));
        if (str_contains($extra, 'auto_increment')) {
            return;
        }

        $columnType = trim((string) ($row->COLUMN_TYPE ?? ''));
        if ($columnType === '') {
            $columnType = 'bigint(20) unsigned';
        }

        $hasPrimaryOnId = DB::selectOne(
            'SELECT 1 AS ok
             FROM information_schema.statistics
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND INDEX_NAME = "PRIMARY"
             LIMIT 1',
            [$database, 'jobs', 'id']
        );

        if (! $hasPrimaryOnId) {
            DB::statement('ALTER TABLE `jobs` ADD PRIMARY KEY (`id`)');
        }

        DB::statement("ALTER TABLE `jobs` MODIFY COLUMN `id` {$columnType} NOT NULL AUTO_INCREMENT");
    }

    public function down(): void
    {
        //
    }
};
