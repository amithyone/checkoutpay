<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            if (! Schema::hasColumn('businesses', 'use_own_cac_for_temp_va')) {
                $table->boolean('use_own_cac_for_temp_va')
                    ->default(false)
                    ->after('uses_external_account_numbers');
            }
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            if (Schema::hasColumn('businesses', 'use_own_cac_for_temp_va')) {
                $table->dropColumn('use_own_cac_for_temp_va');
            }
        });
    }
};
