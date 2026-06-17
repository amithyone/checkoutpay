<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_wallets', 'business_balance')) {
                $table->decimal('business_balance', 14, 2)->default(0)->after('balance');
            }
            if (! Schema::hasColumn('whatsapp_wallets', 'linked_business_id')) {
                $table->unsignedBigInteger('linked_business_id')->nullable()->after('active_business_name_registration_id');
                $table->foreign('linked_business_id')
                    ->references('id')
                    ->on('businesses')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_wallets', 'linked_business_id')) {
                $table->dropConstrainedForeignId('linked_business_id');
            }
            if (Schema::hasColumn('whatsapp_wallets', 'business_balance')) {
                $table->dropColumn('business_balance');
            }
        });
    }
};
