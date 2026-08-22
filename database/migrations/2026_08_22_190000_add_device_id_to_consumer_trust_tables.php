<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumer_trusted_devices', function (Blueprint $table) {
            if (! Schema::hasColumn('consumer_trusted_devices', 'device_id')) {
                $table->string('device_id', 128)->nullable()->after('consumer_wallet_api_account_id');
                $table->timestamp('kyc_confirmed_at')->nullable()->after('last_active_at');
                $table->index(['consumer_wallet_api_account_id', 'device_id'], 'consumer_trusted_devices_account_device_idx');
            }
        });

        Schema::table('consumer_app_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('consumer_app_sessions', 'device_id')) {
                $table->string('device_id', 128)->nullable()->after('device_label');
                $table->index('device_id', 'consumer_app_sessions_device_id_idx');
            }
        });

        Schema::table('consumer_wallet_api_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('consumer_wallet_api_accounts', 'pin_reset_required')) {
                $table->boolean('pin_reset_required')->default(false)->after('transfer_lock_until');
            }
        });

        Schema::table('consumer_device_stepup_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('consumer_device_stepup_sessions', 'pending_device_id')) {
                $table->string('pending_device_id', 128)->nullable()->after('whatsapp_wallet_id');
                $table->string('pending_platform', 32)->nullable()->after('pending_device_id');
                $table->string('pending_device_label', 120)->nullable()->after('pending_platform');
                $table->boolean('pin_set_at_stepup')->default(false)->after('otp_verified_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('consumer_device_stepup_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('consumer_device_stepup_sessions', 'pending_device_id')) {
                $table->dropColumn(['pending_device_id', 'pending_platform', 'pending_device_label', 'pin_set_at_stepup']);
            }
        });

        Schema::table('consumer_wallet_api_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('consumer_wallet_api_accounts', 'pin_reset_required')) {
                $table->dropColumn('pin_reset_required');
            }
        });

        Schema::table('consumer_app_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('consumer_app_sessions', 'device_id')) {
                $table->dropIndex('consumer_app_sessions_device_id_idx');
                $table->dropColumn('device_id');
            }
        });

        Schema::table('consumer_trusted_devices', function (Blueprint $table) {
            if (Schema::hasColumn('consumer_trusted_devices', 'device_id')) {
                $table->dropIndex('consumer_trusted_devices_account_device_idx');
                $table->dropColumn(['device_id', 'kyc_confirmed_at']);
            }
        });
    }
};
