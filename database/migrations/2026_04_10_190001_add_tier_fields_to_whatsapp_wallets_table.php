<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            $table->unsignedTinyInteger('tier')->default(1)->after('renter_id');
            $table->decimal('daily_transfer_total', 14, 2)->default(0)->after('balance');
            $table->date('daily_transfer_for_date')->nullable()->after('daily_transfer_total');
            $table->string('mevon_reference')->nullable()->after('mevon_bank_code');
            $table->timestamp('tier2_provisioned_at')->nullable()->after('mevon_reference');
            $table->string('kyc_fname', 128)->nullable()->after('tier2_provisioned_at');
            $table->string('kyc_lname', 128)->nullable();
            $table->date('kyc_dob')->nullable();
            $table->string('kyc_bvn', 16)->nullable();
            $table->string('kyc_email')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            $table->dropColumn([
                'tier',
                'daily_transfer_total',
                'daily_transfer_for_date',
                'mevon_reference',
                'tier2_provisioned_at',
                'kyc_fname',
                'kyc_lname',
                'kyc_dob',
                'kyc_bvn',
                'kyc_email',
            ]);
        });
    }
};
