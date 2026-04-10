<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->string('magic_link_token_hash', 64)->nullable()->after('otp_attempts');
            $table->timestamp('magic_link_expires_at')->nullable()->after('magic_link_token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->dropColumn(['magic_link_token_hash', 'magic_link_expires_at']);
        });
    }
};
