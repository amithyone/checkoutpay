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
            // Check if webhook_url exists, if so add after it, otherwise add at end
            if (Schema::hasColumn('business_websites', 'webhook_url')) {
                $table->decimal('charge_percentage', 5, 2)->nullable()->after('webhook_url')->comment('Charge percentage (default: 1%)');
            } else {
                $table->decimal('charge_percentage', 5, 2)->nullable()->comment('Charge percentage (default: 1%)');
            }
            $table->decimal('charge_fixed', 10, 2)->nullable()->after('charge_percentage')->comment('Fixed charge amount (default: 100)');
            $table->boolean('charges_paid_by_customer')->default(false)->after('charge_fixed')->comment('Whether customer pays charges (default: false, business pays)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_websites', function (Blueprint $table) {
            $table->dropColumn([
                'charge_percentage',
                'charge_fixed',
                'charges_paid_by_customer',
            ]);
        });
    }
};
