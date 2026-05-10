<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            $table->string('rubies_account_type', 16)->nullable()->after('kyc_email');
            $table->string('kyc_cac', 100)->nullable()->after('rubies_account_type');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            $table->dropColumn(['rubies_account_type', 'kyc_cac']);
        });
    }
};
