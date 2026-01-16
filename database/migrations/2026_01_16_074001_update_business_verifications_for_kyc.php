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
        Schema::table('business_verifications', function (Blueprint $table) {
            // Revert to original enum
            DB::statement("ALTER TABLE business_verifications MODIFY COLUMN verification_type ENUM('basic', 'business_registration', 'bank_account', 'identity', 'address') DEFAULT 'basic'");
        });
    }
};
