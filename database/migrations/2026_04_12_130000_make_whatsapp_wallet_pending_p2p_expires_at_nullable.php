<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_wallet_pending_p2p_credits')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `whatsapp_wallet_pending_p2p_credits` MODIFY `expires_at` TIMESTAMP NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('whatsapp_wallet_pending_p2p_credits')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `whatsapp_wallet_pending_p2p_credits` MODIFY `expires_at` TIMESTAMP NOT NULL');
    }
};
