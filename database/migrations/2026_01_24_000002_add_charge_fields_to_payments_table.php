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
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('charge_percentage', 5, 2)->nullable()->after('amount');
            $table->decimal('charge_fixed', 10, 2)->nullable()->after('charge_percentage');
            $table->decimal('total_charges', 10, 2)->nullable()->after('charge_fixed');
            $table->decimal('business_receives', 10, 2)->nullable()->after('total_charges');
            $table->boolean('charges_paid_by_customer')->default(false)->after('business_receives');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'charge_percentage',
                'charge_fixed',
                'total_charges',
                'business_receives',
                'charges_paid_by_customer',
            ]);
        });
    }
};
