<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_salary_schedules')) {
            return;
        }

        // MySQL enum: allow daily trickle schedules.
        DB::statement("ALTER TABLE business_salary_schedules MODIFY COLUMN cadence ENUM('daily', 'weekly', 'biweekly', 'monthly') NOT NULL DEFAULT 'weekly'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('business_salary_schedules')) {
            return;
        }

        DB::table('business_salary_schedules')->where('cadence', 'daily')->update(['cadence' => 'weekly']);
        DB::statement("ALTER TABLE business_salary_schedules MODIFY COLUMN cadence ENUM('weekly', 'biweekly', 'monthly') NOT NULL DEFAULT 'weekly'");
    }
};
