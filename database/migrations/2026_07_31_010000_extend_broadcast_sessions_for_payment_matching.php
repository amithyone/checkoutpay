<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('broadcast_sessions')) {
            Schema::table('broadcast_sessions', function (Blueprint $table) {
                if (! Schema::hasColumn('broadcast_sessions', 'amount_received_ngn')) {
                    $table->unsignedInteger('amount_received_ngn')->default(0)->after('amount_ngn');
                }
                if (! Schema::hasColumn('broadcast_sessions', 'expecting_payment_at')) {
                    $table->unsignedBigInteger('expecting_payment_at')->nullable()->after('opened_at');
                }
                if (! Schema::hasColumn('broadcast_sessions', 'settlement_mode')) {
                    $table->string('settlement_mode', 16)->default('permanent')->after('status');
                }
                if (! Schema::hasColumn('broadcast_sessions', 'payment_id')) {
                    $table->unsignedBigInteger('payment_id')->nullable()->after('closed_at');
                }
                if (! Schema::hasColumn('broadcast_sessions', 'settlement_account_number')) {
                    $table->string('settlement_account_number', 16)->nullable()->after('payment_id');
                }
            });
        }

        if (Schema::hasTable('businesses')) {
            Schema::table('businesses', function (Blueprint $table) {
                if (! Schema::hasColumn('businesses', 'broadcast_pay_at_shop_permanent_settlement')) {
                    $table->boolean('broadcast_pay_at_shop_permanent_settlement')
                        ->default(true)
                        ->after('broadcast_pay_at_shop_active');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('broadcast_sessions')) {
            Schema::table('broadcast_sessions', function (Blueprint $table) {
                foreach ([
                    'amount_received_ngn',
                    'expecting_payment_at',
                    'settlement_mode',
                    'payment_id',
                    'settlement_account_number',
                ] as $col) {
                    if (Schema::hasColumn('broadcast_sessions', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('businesses')) {
            Schema::table('businesses', function (Blueprint $table) {
                if (Schema::hasColumn('businesses', 'broadcast_pay_at_shop_permanent_settlement')) {
                    $table->dropColumn('broadcast_pay_at_shop_permanent_settlement');
                }
            });
        }
    }
};
