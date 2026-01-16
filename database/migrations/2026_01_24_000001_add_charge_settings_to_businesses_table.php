<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->boolean('charges_paid_by_customer')->default(false)->after('auto_withdraw_threshold');
            $table->decimal('charge_percentage', 5, 2)->nullable()->after('charges_paid_by_customer');
            $table->decimal('charge_fixed', 10, 2)->nullable()->after('charge_percentage');
            $table->boolean('charge_exempt')->default(false)->after('charge_fixed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'charges_paid_by_customer',
                'charge_percentage',
                'charge_fixed',
                'charge_exempt',
            ]);
        });
    }
};
