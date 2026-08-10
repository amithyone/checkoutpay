<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            if (! Schema::hasColumn('businesses', 'rubies_signatory_name')) {
                $table->string('rubies_signatory_name', 255)->nullable()->after('rubies_signatory_dob');
            }
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            if (Schema::hasColumn('businesses', 'rubies_signatory_name')) {
                $table->dropColumn('rubies_signatory_name');
            }
        });
    }
};
