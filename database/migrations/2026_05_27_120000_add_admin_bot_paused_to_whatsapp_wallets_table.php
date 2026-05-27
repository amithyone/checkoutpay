<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_wallets', function (Blueprint $table): void {
            $table->boolean('admin_bot_paused')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_wallets', function (Blueprint $table): void {
            $table->dropColumn('admin_bot_paused');
        });
    }
};
