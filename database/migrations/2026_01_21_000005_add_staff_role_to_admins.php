<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update the enum to include 'staff' role
        DB::statement("ALTER TABLE admins MODIFY COLUMN role ENUM('super_admin', 'admin', 'support', 'staff') DEFAULT 'admin'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'staff' role from enum
        DB::statement("ALTER TABLE admins MODIFY COLUMN role ENUM('super_admin', 'admin', 'support') DEFAULT 'admin'");
    }
};
