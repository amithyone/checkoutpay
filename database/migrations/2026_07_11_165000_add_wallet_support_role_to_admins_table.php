<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE admins MODIFY COLUMN role ENUM('super_admin', 'admin', 'support', 'staff', 'tax', 'wallet_support') DEFAULT 'admin'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('admins')->where('role', 'wallet_support')->update(['role' => 'support']);

        DB::statement("ALTER TABLE admins MODIFY COLUMN role ENUM('super_admin', 'admin', 'support', 'staff', 'tax') DEFAULT 'admin'");
    }
};
