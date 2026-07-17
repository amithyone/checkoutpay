<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rentals_admin_app_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('session_uuid')->unique();
            $table->unsignedBigInteger('admin_id')->nullable()->index();
            $table->string('admin_email', 191)->nullable()->index();
            $table->string('admin_name', 160)->nullable();
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

            $table->foreign('admin_id', 'rentals_admin_app_sess_admin_fk')
                ->references('id')->on('admins')->nullOnDelete();
        });

        Schema::create('rentals_admin_app_session_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rentals_admin_app_session_id')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable()->index();
            $table->string('admin_email', 191)->nullable()->index();
            $table->string('event_type', 48)->index();
            $table->string('summary', 255);
            $table->json('meta')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['rentals_admin_app_session_id', 'created_at'], 'rentals_admin_app_sess_ev_sess_created');
            $table->foreign('rentals_admin_app_session_id', 'rentals_admin_app_sess_ev_sess_fk')
                ->references('id')->on('rentals_admin_app_sessions')->nullOnDelete();
            $table->foreign('admin_id', 'rentals_admin_app_sess_ev_admin_fk')
                ->references('id')->on('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rentals_admin_app_session_events');
        Schema::dropIfExists('rentals_admin_app_sessions');
    }
};
