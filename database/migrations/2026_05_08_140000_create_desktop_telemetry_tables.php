<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('desktop_telemetry_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 64)->index();
            $table->string('app_role', 16);
            $table->string('app_instance_id', 128)->index();
            $table->string('event_id', 64);
            $table->string('event_type', 80)->index();
            $table->timestamp('event_ts');
            $table->string('app_version', 64)->nullable();
            $table->json('payload_json')->nullable();
            $table->json('context_json')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();
            $table->unique(['app_instance_id', 'event_id'], 'desktop_event_unique');
        });

        Schema::create('desktop_policies', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 64)->index();
            $table->string('scope_type', 16);
            $table->string('scope_value', 128);
            $table->boolean('locked')->default(false);
            $table->string('lock_reason_code', 80)->nullable();
            $table->timestamp('lock_at')->nullable();
            $table->timestamp('grace_until')->nullable();
            $table->unsignedInteger('min_heartbeat_seconds')->default(300);
            $table->text('admin_notes')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'scope_type', 'scope_value'], 'desktop_policy_scope_unique');
        });

        Schema::create('desktop_app_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('tenant_id', 64)->default('default-tenant')->index();
            $table->string('bearer_token', 96)->unique();
            $table->string('hmac_secret', 96);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('desktop_app_tokens');
        Schema::dropIfExists('desktop_policies');
        Schema::dropIfExists('desktop_telemetry_events');
    }
};
