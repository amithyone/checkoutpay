<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_lending_offers', function (Blueprint $table) {
            $table->string('repayment_frequency', 20)
                ->default('weekly')
                ->after('repayment_type');
        });
    }

    public function down(): void
    {
        Schema::table('business_lending_offers', function (Blueprint $table) {
            $table->dropColumn('repayment_frequency');
        });
    }
};
