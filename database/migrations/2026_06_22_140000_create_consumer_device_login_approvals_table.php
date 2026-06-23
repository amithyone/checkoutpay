<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumer_device_login_approvals', function (Blueprint $table) {
            $table->id();
            $table->string('approval_id', 64)->unique();
            $table->unsignedBigInteger('consumer_device_stepup_session_id');
            $table->unsignedBigInteger('consumer_wallet_api_account_id');
            $table->string('status', 16)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('consumer_device_stepup_session_id', 'consumer_login_approvals_stepup_fk')
                ->references('id')
                ->on('consumer_device_stepup_sessions')
                ->cascadeOnDelete();
            $table->foreign('consumer_wallet_api_account_id', 'consumer_login_approvals_account_fk')
                ->references('id')
                ->on('consumer_wallet_api_accounts')
                ->cascadeOnDelete();
            $table->index(['consumer_device_stepup_session_id', 'status'], 'consumer_login_approvals_stepup_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumer_device_login_approvals');
    }
};
