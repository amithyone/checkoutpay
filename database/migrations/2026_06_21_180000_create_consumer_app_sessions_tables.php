<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumer_app_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('session_uuid')->unique();
            $table->unsignedBigInteger('consumer_wallet_api_account_id')->nullable();
            $table->unsignedBigInteger('whatsapp_wallet_id')->nullable();
            $table->string('phone_e164', 20)->nullable()->index();
            $table->string('login_method', 32);
            $table->string('platform', 16)->nullable();
            $table->string('app_version', 64)->nullable();
            $table->string('device_label', 160)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->unsignedBigInteger('personal_access_token_id')->nullable()->index();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable()->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();

            $table->foreign('consumer_wallet_api_account_id', 'consumer_app_sess_acct_fk')
                ->references('id')->on('consumer_wallet_api_accounts')->nullOnDelete();
            $table->foreign('whatsapp_wallet_id', 'consumer_app_sess_wallet_fk')
                ->references('id')->on('whatsapp_wallets')->nullOnDelete();
        });

        Schema::create('consumer_app_session_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('consumer_app_session_id')->nullable();
            $table->unsignedBigInteger('consumer_wallet_api_account_id')->nullable();
            $table->unsignedBigInteger('whatsapp_wallet_id')->nullable();
            $table->string('phone_e164', 20)->nullable()->index();
            $table->string('event_type', 48)->index();
            $table->string('summary', 255);
            $table->json('meta')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['consumer_app_session_id', 'created_at'], 'consumer_app_sess_ev_sess_created');
            $table->foreign('consumer_app_session_id', 'consumer_app_sess_ev_sess_fk')
                ->references('id')->on('consumer_app_sessions')->nullOnDelete();
            $table->foreign('consumer_wallet_api_account_id', 'consumer_app_sess_ev_acct_fk')
                ->references('id')->on('consumer_wallet_api_accounts')->nullOnDelete();
            $table->foreign('whatsapp_wallet_id', 'consumer_app_sess_ev_wallet_fk')
                ->references('id')->on('whatsapp_wallets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumer_app_session_events');
        Schema::dropIfExists('consumer_app_sessions');
    }
};
