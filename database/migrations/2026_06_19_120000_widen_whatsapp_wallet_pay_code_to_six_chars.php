<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('whatsapp_wallets', 'pay_code')) {
            return;
        }

        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            $table->char('pay_code', 6)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('whatsapp_wallets', 'pay_code')) {
            return;
        }

        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            $table->char('pay_code', 5)->nullable()->change();
        });
    }
};
