<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumer_wallet_api_accounts', function (Blueprint $table) {
            $table->string('fcm_token', 512)->nullable()->after('phone_e164');
            $table->string('fcm_platform', 16)->nullable()->after('fcm_token');
            $table->timestamp('fcm_token_updated_at')->nullable()->after('fcm_platform');
        });
    }

    public function down(): void
    {
        Schema::table('consumer_wallet_api_accounts', function (Blueprint $table) {
            $table->dropColumn(['fcm_token', 'fcm_platform', 'fcm_token_updated_at']);
        });
    }
};
