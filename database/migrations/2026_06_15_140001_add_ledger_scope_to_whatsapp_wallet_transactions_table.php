<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_wallet_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_wallet_transactions', 'ledger_scope')) {
                $table->string('ledger_scope', 16)->default('personal')->after('type');
                $table->index(['whatsapp_wallet_id', 'ledger_scope', 'id'], 'wwt_wallet_scope_id_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_wallet_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_wallet_transactions', 'ledger_scope')) {
                $table->dropIndex('wwt_wallet_scope_id_idx');
                $table->dropColumn('ledger_scope');
            }
        });
    }
};
