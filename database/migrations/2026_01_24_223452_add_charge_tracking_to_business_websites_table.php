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
            $table->boolean('charges_enabled')->default(true)->after('charges_paid_by_customer');
            $table->decimal('total_charges_collected', 15, 2)->default(0)->after('charges_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_websites', function (Blueprint $table) {
            $table->dropColumn(['charges_enabled', 'total_charges_collected']);
        });
    }
};
