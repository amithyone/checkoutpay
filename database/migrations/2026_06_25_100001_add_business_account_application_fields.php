<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_wallets') && ! Schema::hasColumn('whatsapp_wallets', 'active_business_account_application_id')) {
            Schema::table('whatsapp_wallets', function (Blueprint $table) {
                $table->unsignedBigInteger('active_business_account_application_id')->nullable()->after('active_business_name_registration_id');
                $table->foreign('active_business_account_application_id', 'ww_active_baa_fk')
                    ->references('id')
                    ->on('business_account_applications')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('businesses') && ! Schema::hasColumn('businesses', 'service_categories')) {
            Schema::table('businesses', function (Blueprint $table) {
                $table->json('service_categories')->nullable()->after('website');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('whatsapp_wallets') && Schema::hasColumn('whatsapp_wallets', 'active_business_account_application_id')) {
            Schema::table('whatsapp_wallets', function (Blueprint $table) {
                $table->dropForeign('ww_active_baa_fk');
                $table->dropColumn('active_business_account_application_id');
            });
        }

        if (Schema::hasTable('businesses') && Schema::hasColumn('businesses', 'service_categories')) {
            Schema::table('businesses', function (Blueprint $table) {
                $table->dropColumn('service_categories');
            });
        }
    }
};
