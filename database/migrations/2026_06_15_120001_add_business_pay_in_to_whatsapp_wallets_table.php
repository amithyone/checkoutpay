<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            $table->string('business_pay_in_account_number', 32)->nullable()->after('mevon_reference');
            $table->string('business_pay_in_account_name', 200)->nullable()->after('business_pay_in_account_number');
            $table->string('business_pay_in_bank_name', 120)->nullable()->after('business_pay_in_account_name');
            $table->string('business_pay_in_bank_code', 16)->nullable()->after('business_pay_in_bank_name');
            $table->foreignId('active_business_name_registration_id')
                ->nullable()
                ->after('business_pay_in_bank_code')
                ->constrained('business_name_registrations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('active_business_name_registration_id');
            $table->dropColumn([
                'business_pay_in_account_number',
                'business_pay_in_account_name',
                'business_pay_in_bank_name',
                'business_pay_in_bank_code',
            ]);
        });
    }
};
