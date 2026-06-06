<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('virtual_card_requests') && ! Schema::hasColumn('virtual_card_requests', 'provider_reference')) {
            Schema::table('virtual_card_requests', function (Blueprint $table) {
                $table->string('provider_reference', 128)->nullable()->after('external_reference');
                $table->index('provider_reference');
            });
        }

        if (! Schema::hasTable('virtual_card_request_logs')) {
            Schema::create('virtual_card_request_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('virtual_card_request_id')->nullable()->constrained('virtual_card_requests')->nullOnDelete();
                $table->foreignId('whatsapp_wallet_id')->nullable()->constrained('whatsapp_wallets')->nullOnDelete();
                $table->string('level', 16)->default('info');
                $table->string('event', 64);
                $table->string('message', 500);
                $table->json('context')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['virtual_card_request_id', 'created_at'], 'vcard_logs_request_created_idx');
                $table->index(['event', 'created_at'], 'vcard_logs_event_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('virtual_card_request_logs');

        if (Schema::hasTable('virtual_card_requests') && Schema::hasColumn('virtual_card_requests', 'provider_reference')) {
            Schema::table('virtual_card_requests', function (Blueprint $table) {
                $table->dropIndex(['provider_reference']);
                $table->dropColumn('provider_reference');
            });
        }
    }
};
