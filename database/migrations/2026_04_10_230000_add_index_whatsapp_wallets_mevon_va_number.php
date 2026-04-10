<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            $table->index('mevon_virtual_account_number', 'ww_mevon_va_number_idx');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            $table->dropIndex('ww_mevon_va_number_idx');
        });
    }
};
