<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('businesses')) {
            return;
        }

        Schema::table('businesses', function (Blueprint $table) {
            if (! Schema::hasColumn('businesses', 'broadcast_pay_at_shop_enabled')) {
                $table->boolean('broadcast_pay_at_shop_enabled')->default(false)->after('card_payments_enabled');
            }
            if (! Schema::hasColumn('businesses', 'broadcast_pay_at_shop_active')) {
                $table->boolean('broadcast_pay_at_shop_active')->default(false)->after('broadcast_pay_at_shop_enabled');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('businesses')) {
            return;
        }

        Schema::table('businesses', function (Blueprint $table) {
            if (Schema::hasColumn('businesses', 'broadcast_pay_at_shop_active')) {
                $table->dropColumn('broadcast_pay_at_shop_active');
            }
            if (Schema::hasColumn('businesses', 'broadcast_pay_at_shop_enabled')) {
                $table->dropColumn('broadcast_pay_at_shop_enabled');
            }
        });
    }
};
