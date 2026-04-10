<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->string('chat_flow', 64)->nullable()->after('renter_id');
            $table->json('chat_context')->nullable()->after('chat_flow');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->dropColumn(['chat_flow', 'chat_context']);
        });
    }
};
