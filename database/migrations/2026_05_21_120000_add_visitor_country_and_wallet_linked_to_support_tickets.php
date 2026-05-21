<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->char('visitor_country', 2)->nullable()->after('visitor_phone');
            $table->boolean('wallet_linked')->default(false)->after('whatsapp_wallet_id');
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropColumn(['visitor_country', 'wallet_linked']);
        });
    }
};
