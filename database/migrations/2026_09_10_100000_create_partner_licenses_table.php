<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_licenses', function (Blueprint $table) {
            $table->id();
            $table->string('partner_name');
            $table->string('license_key', 80)->unique();
            $table->json('allowed_hosts')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->string('min_build_version', 32)->default('1.0.0');
            $table->json('features')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('last_ping_host')->nullable();
            $table->string('last_build_version', 32)->nullable();
            $table->string('last_build_id', 64)->nullable();
            $table->timestamp('last_ping_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['revoked_at', 'valid_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_licenses');
    }
};
