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
            $table->decimal('daily_revenue', 15, 2)->default(0)->after('balance');
            $table->decimal('monthly_revenue', 15, 2)->default(0)->after('daily_revenue');
            $table->decimal('yearly_revenue', 15, 2)->default(0)->after('monthly_revenue');
            $table->dateTime('last_daily_revenue_update')->nullable()->after('yearly_revenue');
            $table->dateTime('last_monthly_revenue_update')->nullable()->after('last_daily_revenue_update');
            $table->dateTime('last_yearly_revenue_update')->nullable()->after('last_monthly_revenue_update');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'daily_revenue',
                'monthly_revenue',
                'yearly_revenue',
                'last_daily_revenue_update',
                'last_monthly_revenue_update',
                'last_yearly_revenue_update',
            ]);
        });
    }
};
