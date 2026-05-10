<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed GTBank email template
        $this->call([
            PageSeeder::class,
            GtbankTemplateSeeder::class,
            EmailTemplateSeeder::class,
            SuperAdminBusinessSeeder::class,
            EventSeeder::class,
            MembershipCategorySeeder::class,
        ]);
    }
}
