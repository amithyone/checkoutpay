<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('broadcast_sessions')) {
            return;
        }

        Schema::table('broadcast_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('broadcast_sessions', 'payer_name')) {
                $table->string('payer_name', 191)->nullable()->after('payment_id');
            }
            if (! Schema::hasColumn('broadcast_sessions', 'payer_account')) {
                $table->string('payer_account', 32)->nullable()->after('payer_name');
            }
            if (! Schema::hasColumn('broadcast_sessions', 'payer_bank')) {
                $table->string('payer_bank', 120)->nullable()->after('payer_account');
            }
            if (! Schema::hasColumn('broadcast_sessions', 'payer_reference')) {
                $table->string('payer_reference', 128)->nullable()->after('payer_bank');
            }
            if (! Schema::hasColumn('broadcast_sessions', 'whatsapp_wallet_id')) {
                $table->unsignedBigInteger('whatsapp_wallet_id')->nullable()->after('payer_reference');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('broadcast_sessions')) {
            return;
        }

        Schema::table('broadcast_sessions', function (Blueprint $table) {
            foreach (['payer_name', 'payer_account', 'payer_bank', 'payer_reference', 'whatsapp_wallet_id'] as $col) {
                if (Schema::hasColumn('broadcast_sessions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
