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
        Schema::table('business_websites', function (Blueprint $table) {
            $table->decimal('monthly_revenue', 15, 2)->default(0)->after('charges_paid_by_customer');
            $table->decimal('yearly_revenue', 15, 2)->default(0)->after('monthly_revenue');
            $table->dateTime('last_monthly_revenue_update')->nullable()->after('yearly_revenue');
            $table->dateTime('last_yearly_revenue_update')->nullable()->after('last_monthly_revenue_update');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_websites', function (Blueprint $table) {
            $table->dropColumn([
                'monthly_revenue',
                'yearly_revenue',
                'last_monthly_revenue_update',
                'last_yearly_revenue_update',
            ]);
        });
    }
};
