<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * charge_percentage stores the percentage charge amount (e.g. 1550 for 1% of 155000),
     * not the rate. decimal(5,2) max 999.99 caused "Out of range" for larger payments.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('charge_percentage', 12, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('charge_percentage', 5, 2)->nullable()->change();
        });
    }
};
