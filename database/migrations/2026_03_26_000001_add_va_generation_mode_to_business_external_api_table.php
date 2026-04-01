<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_external_api', function (Blueprint $table) {
            if (!Schema::hasColumn('business_external_api', 'va_generation_mode')) {
                // Where the system decides which MEVONPAY VA endpoint to call for external-only/hybrid.
                // - dynamic    => createdynamic (amount+currency)
                // - temp       => createtempva (requires fname/lname/bvn)
                $table->string('va_generation_mode', 20)->default('dynamic')->after('services');
            }
        });
    }

    public function down(): void
    {
        Schema::table('business_external_api', function (Blueprint $table) {
            if (Schema::hasColumn('business_external_api', 'va_generation_mode')) {
                $table->dropColumn('va_generation_mode');
            }
        });
    }
};

