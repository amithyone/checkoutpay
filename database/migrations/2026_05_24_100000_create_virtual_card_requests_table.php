<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virtual_card_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_wallet_id')->constrained('whatsapp_wallets')->cascadeOnDelete();
            $table->string('status', 32)->default('pending');
            $table->decimal('fee_usd', 10, 2)->default(5);
            $table->decimal('fee_ngn', 14, 2);
            $table->decimal('fx_rate_used', 18, 6)->nullable();
            $table->string('external_reference', 64)->nullable()->index();
            $table->string('card_external_id', 128)->nullable();
            $table->string('card_name', 120)->nullable();
            $table->string('home_number', 32)->nullable();
            $table->string('home_address', 255)->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['whatsapp_wallet_id', 'status']);
        });

        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_wallets', 'card_home_number')) {
                $table->string('card_home_number', 32)->nullable()->after('kyc_email');
            }
            if (! Schema::hasColumn('whatsapp_wallets', 'card_home_address')) {
                $table->string('card_home_address', 255)->nullable()->after('card_home_number');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('virtual_card_requests');

        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_wallets', 'card_home_address')) {
                $table->dropColumn('card_home_address');
            }
            if (Schema::hasColumn('whatsapp_wallets', 'card_home_number')) {
                $table->dropColumn('card_home_number');
            }
        });
    }
};
