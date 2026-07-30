<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('broadcast_terminals')) {
            return;
        }

        Schema::table('broadcast_terminals', function (Blueprint $table) {
            if (! Schema::hasColumn('broadcast_terminals', 'merchant_id')) {
                $table->string('merchant_id', 64)->nullable()->after('terminal_id');
            }
            if (! Schema::hasColumn('broadcast_terminals', 'api_key')) {
                $table->string('api_key', 64)->nullable()->unique('broadcast_terminals_api_key_unique')->after('merchant_id');
            }
            if (! Schema::hasColumn('broadcast_terminals', 'public_key')) {
                $table->string('public_key', 128)->nullable()->after('signing_key');
            }
            if (! Schema::hasColumn('broadcast_terminals', 'signature_alg')) {
                $table->string('signature_alg', 32)->default('HMAC-SHA256')->after('public_key');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('broadcast_terminals')) {
            return;
        }

        Schema::table('broadcast_terminals', function (Blueprint $table) {
            if (Schema::hasColumn('broadcast_terminals', 'signature_alg')) {
                $table->dropColumn('signature_alg');
            }
            if (Schema::hasColumn('broadcast_terminals', 'public_key')) {
                $table->dropColumn('public_key');
            }
            if (Schema::hasColumn('broadcast_terminals', 'api_key')) {
                $table->dropUnique('broadcast_terminals_api_key_unique');
                $table->dropColumn('api_key');
            }
            if (Schema::hasColumn('broadcast_terminals', 'merchant_id')) {
                $table->dropColumn('merchant_id');
            }
        });
    }
};
