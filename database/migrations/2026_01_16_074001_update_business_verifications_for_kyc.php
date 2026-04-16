<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite (used in tests) doesn't support MODIFY COLUMN / ENUM the way MySQL does.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if (!Schema::hasTable('business_verifications')) {
            return;
        }

        Schema::table('business_verifications', function (Blueprint $table) {
            // Update enum to include new verification types
            DB::statement("ALTER TABLE business_verifications MODIFY COLUMN verification_type ENUM('basic', 'business_registration', 'bank_account', 'identity', 'address', 'bvn', 'nin', 'cac_certificate', 'cac_application', 'account_number', 'bank_address', 'utility_bill') DEFAULT 'basic'");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if (!Schema::hasTable('business_verifications')) {
            return;
        }

        Schema::table('business_verifications', function (Blueprint $table) {
            // Revert to original enum
            DB::statement("ALTER TABLE business_verifications MODIFY COLUMN verification_type ENUM('basic', 'business_registration', 'bank_account', 'identity', 'address') DEFAULT 'basic'");
        });
    }
};
