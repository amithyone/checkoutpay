<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_wallets', 'kyc_national_id')) {
                $table->string('kyc_national_id', 32)->nullable()->after('kyc_bvn');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_wallets', 'kyc_national_id')) {
                $table->dropColumn('kyc_national_id');
            }
        });
    }
};
