<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            if (!Schema::hasColumn('businesses', 'uses_external_account_numbers')) {
                $table->boolean('uses_external_account_numbers')
                    ->default(true)
                    ->after('webhook_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            if (Schema::hasColumn('businesses', 'uses_external_account_numbers')) {
                $table->dropColumn('uses_external_account_numbers');
            }
        });
    }
};
