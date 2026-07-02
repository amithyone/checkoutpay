<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('virtual_card_requests')) {
            return;
        }

        Schema::table('virtual_card_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('virtual_card_requests', 'provider')) {
                $table->string('provider', 32)->default('mevonpay')->after('status');
                $table->index('provider');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('virtual_card_requests')) {
            return;
        }

        Schema::table('virtual_card_requests', function (Blueprint $table) {
            if (Schema::hasColumn('virtual_card_requests', 'provider')) {
                $table->dropIndex(['provider']);
                $table->dropColumn('provider');
            }
        });
    }
};
