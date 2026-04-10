<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_wallet_pending_topups', function (Blueprint $table) {
            $table->foreignId('payment_id')
                ->nullable()
                ->after('whatsapp_wallet_id')
                ->constrained('payments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_wallet_pending_topups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_id');
        });
    }
};
