<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_external_api', function (Blueprint $table) {
            if (!Schema::hasColumn('business_external_api', 'assignment_mode')) {
                $table->string('assignment_mode', 30)->default('hybrid')->after('external_api_id');
            }
            if (!Schema::hasColumn('business_external_api', 'services')) {
                $table->json('services')->nullable()->after('assignment_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('business_external_api', function (Blueprint $table) {
            if (Schema::hasColumn('business_external_api', 'services')) {
                $table->dropColumn('services');
            }
            if (Schema::hasColumn('business_external_api', 'assignment_mode')) {
                $table->dropColumn('assignment_mode');
            }
        });
    }
};
