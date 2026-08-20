<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_wallets', 'referred_by_wallet_id')) {
                $table->unsignedBigInteger('referred_by_wallet_id')->nullable()->after('pay_code');
                $table->index('referred_by_wallet_id');
            }
        });

        if (! Schema::hasTable('whatsapp_wallet_referrals')) {
            Schema::create('whatsapp_wallet_referrals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('referred_wallet_id')->unique();
                $table->unsignedBigInteger('referrer_wallet_id')->index();
                $table->string('attribution_source', 32);
                $table->string('referral_code_used', 64)->nullable();
                $table->timestamp('attributed_at')->useCurrent();
                $table->timestamp('bonus_ends_at')->useCurrent();
                $table->unsignedInteger('counted_tx_total')->default(0);
                $table->unsignedInteger('milestones_paid')->default(0);
                $table->timestamp('first_deposit_bonus_paid_at')->nullable();
                $table->timestamps();

                $table->foreign('referred_wallet_id')
                    ->references('id')->on('whatsapp_wallets')->cascadeOnDelete();
                $table->foreign('referrer_wallet_id')
                    ->references('id')->on('whatsapp_wallets')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('whatsapp_wallet_referral_bonuses')) {
            Schema::create('whatsapp_wallet_referral_bonuses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('referral_id')->index();
                $table->unsignedBigInteger('referrer_wallet_id')->index();
                $table->unsignedBigInteger('referred_wallet_id')->index();
                $table->string('type', 40);
                $table->decimal('amount', 14, 2);
                $table->string('currency', 8)->default('NGN');
                $table->string('idempotency_key', 120)->unique();
                $table->json('meta')->nullable();
                $table->unsignedBigInteger('wallet_transaction_id')->nullable()->index();
                $table->timestamps();

                $table->foreign('referral_id')
                    ->references('id')->on('whatsapp_wallet_referrals')->cascadeOnDelete();
                $table->foreign('referrer_wallet_id')
                    ->references('id')->on('whatsapp_wallets')->cascadeOnDelete();
                $table->foreign('referred_wallet_id')
                    ->references('id')->on('whatsapp_wallets')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_wallet_referral_bonuses');
        Schema::dropIfExists('whatsapp_wallet_referrals');

        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_wallets', 'referred_by_wallet_id')) {
                $table->dropColumn('referred_by_wallet_id');
            }
        });
    }
};
