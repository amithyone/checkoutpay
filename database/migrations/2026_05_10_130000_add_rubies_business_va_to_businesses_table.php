<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('cac_registration_number', 100)->nullable();
            $table->date('rubies_signatory_dob')->nullable();
            $table->string('rubies_business_account_number', 64)->nullable();
            $table->string('rubies_business_account_name')->nullable();
            $table->string('rubies_business_bank_name')->nullable();
            $table->string('rubies_business_bank_code', 32)->nullable();
            $table->string('rubies_business_reference')->nullable();
            $table->timestamp('rubies_business_account_created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'cac_registration_number',
                'rubies_signatory_dob',
                'rubies_business_account_number',
                'rubies_business_account_name',
                'rubies_business_bank_name',
                'rubies_business_bank_code',
                'rubies_business_reference',
                'rubies_business_account_created_at',
            ]);
        });
    }
};
