<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('renters', function (Blueprint $table) {
            $table->string('whatsapp_phone_e164', 32)->nullable()->unique()->after('phone');
            $table->timestamp('whatsapp_verified_at')->nullable()->after('whatsapp_phone_e164');
        });
    }

    public function down(): void
    {
        Schema::table('renters', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_phone_e164', 'whatsapp_verified_at']);
        });
    }
};
