<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumer_trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('consumer_wallet_api_account_id');
            $table->string('label', 120)->nullable();
            $table->string('platform', 32)->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();

            $table->foreign('consumer_wallet_api_account_id', 'consumer_trusted_devices_account_fk')
                ->references('id')
                ->on('consumer_wallet_api_accounts')
                ->cascadeOnDelete();
            $table->index(['consumer_wallet_api_account_id', 'last_active_at'], 'consumer_trusted_devices_account_active_idx');
        });

        Schema::create('consumer_passkey_credentials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('consumer_trusted_device_id');
            $table->string('credential_id', 512)->unique();
            $table->json('credential_record');
            $table->unsignedBigInteger('counter')->default(0);
            $table->timestamps();

            $table->foreign('consumer_trusted_device_id', 'consumer_passkey_credentials_device_fk')
                ->references('id')
                ->on('consumer_trusted_devices')
                ->cascadeOnDelete();
        });

        Schema::create('consumer_device_stepup_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_token', 64)->unique();
            $table->unsignedBigInteger('consumer_wallet_api_account_id');
            $table->string('phone_e164', 20);
            $table->unsignedBigInteger('whatsapp_wallet_id');
            $table->timestamp('auth_verified_at')->nullable();
            $table->timestamp('bvn_verified_at')->nullable();
            $table->timestamp('otp_verified_at')->nullable();
            $table->string('stepup_token', 64)->nullable()->unique();
            $table->timestamp('stepup_token_expires_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->foreign('consumer_wallet_api_account_id', 'consumer_stepup_sessions_account_fk')
                ->references('id')
                ->on('consumer_wallet_api_accounts')
                ->cascadeOnDelete();
            $table->foreign('whatsapp_wallet_id', 'consumer_stepup_sessions_wallet_fk')
                ->references('id')
                ->on('whatsapp_wallets')
                ->cascadeOnDelete();
            $table->index(['phone_e164', 'expires_at'], 'consumer_stepup_phone_expires_idx');
        });

        Schema::table('consumer_wallet_api_accounts', function (Blueprint $table) {
            $table->timestamp('transfer_lock_until')->nullable()->after('last_app_active_at');
        });
    }

    public function down(): void
    {
        Schema::table('consumer_wallet_api_accounts', function (Blueprint $table) {
            $table->dropColumn('transfer_lock_until');
        });

        Schema::dropIfExists('consumer_device_stepup_sessions');
        Schema::dropIfExists('consumer_passkey_credentials');
        Schema::dropIfExists('consumer_trusted_devices');
    }
};
