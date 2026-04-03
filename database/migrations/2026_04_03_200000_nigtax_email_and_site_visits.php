<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nigtax_business_records') && !Schema::hasColumn('nigtax_business_records', 'email')) {
            Schema::table('nigtax_business_records', function (Blueprint $table) {
                $table->string('email', 255)->nullable();
            });
        }

        if (Schema::hasTable('nigtax_personal_records') && !Schema::hasColumn('nigtax_personal_records', 'email')) {
            Schema::table('nigtax_personal_records', function (Blueprint $table) {
                $table->string('email', 255)->nullable();
            });
        }

        if (!Schema::hasTable('nigtax_site_visits')) {
            Schema::create('nigtax_site_visits', function (Blueprint $table) {
                $table->id();
                $table->string('path', 512)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('nigtax_site_visits')) {
            Schema::dropIfExists('nigtax_site_visits');
        }

        if (Schema::hasTable('nigtax_business_records') && Schema::hasColumn('nigtax_business_records', 'email')) {
            Schema::table('nigtax_business_records', function (Blueprint $table) {
                $table->dropColumn('email');
            });
        }

        if (Schema::hasTable('nigtax_personal_records') && Schema::hasColumn('nigtax_personal_records', 'email')) {
            Schema::table('nigtax_personal_records', function (Blueprint $table) {
                $table->dropColumn('email');
            });
        }
    }
};
