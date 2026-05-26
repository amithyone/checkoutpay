<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Sync only legal & security CMS pages from database/legal/*.html
 */
class LegalPagesSeeder extends Seeder
{
    public function run(): void
    {
        LegalPagesDefinitions::syncToDatabase();
    }
}
