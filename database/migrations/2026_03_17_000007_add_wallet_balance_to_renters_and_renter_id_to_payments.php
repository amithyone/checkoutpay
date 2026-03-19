<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('renters', 'wallet_balance')) {
            Schema::table('renters', function (Blueprint $table) {
                $table->decimal('wallet_balance', 15, 2)->default(0)->after('address');
                $table->index('wallet_balance');
            });
        }

        if (! Schema::hasColumn('payments', 'renter_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->unsignedBigInteger('renter_id')->nullable()->after('business_id');
                $table->index('renter_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payments', 'renter_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropIndex(['renter_id']);
                $table->dropColumn('renter_id');
            });
        }

        if (Schema::hasColumn('renters', 'wallet_balance')) {
            Schema::table('renters', function (Blueprint $table) {
                $table->dropIndex(['wallet_balance']);
                $table->dropColumn('wallet_balance');
            });
        }
    }
};

