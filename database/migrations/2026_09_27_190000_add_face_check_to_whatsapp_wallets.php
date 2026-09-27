<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_wallets', 'face_enrolled_at')) {
                $table->timestamp('face_enrolled_at')->nullable()->after('locked_down_at');
            }
        });

        if (! Schema::hasTable('whatsapp_wallet_transfer_beneficiaries')) {
            Schema::create('whatsapp_wallet_transfer_beneficiaries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('whatsapp_wallet_id');
                $table->string('kind', 16); // bank|p2p
                $table->string('destination_key', 80);
                $table->string('account_number', 32)->nullable();
                $table->string('bank_code', 20)->nullable();
                $table->string('phone_e164', 20)->nullable();
                $table->string('display_name', 120)->nullable();
                $table->timestamps();

                $table->foreign('whatsapp_wallet_id', 'ww_beneficiaries_wallet_fk')
                    ->references('id')
                    ->on('whatsapp_wallets')
                    ->cascadeOnDelete();
                $table->unique(['whatsapp_wallet_id', 'destination_key'], 'ww_beneficiaries_dest_unique');
                $table->index(['whatsapp_wallet_id', 'kind'], 'ww_beneficiaries_wallet_kind_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_wallet_transfer_beneficiaries');

        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_wallets', 'face_enrolled_at')) {
                $table->dropColumn('face_enrolled_at');
            }
        });
    }
};
