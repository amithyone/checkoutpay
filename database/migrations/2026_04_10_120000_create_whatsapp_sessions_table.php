<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('phone_e164', 32)->unique();
            $table->string('remote_jid', 128);
            $table->string('evolution_instance', 128);
            $table->string('state', 32)->default('welcome');
            $table->string('pending_email')->nullable();
            $table->string('otp_code_hash')->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->unsignedTinyInteger('otp_attempts')->default(0);
            $table->foreignId('renter_id')->nullable()->constrained('renters')->nullOnDelete();
            $table->timestamps();

            $table->index(['state', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_sessions');
    }
};
