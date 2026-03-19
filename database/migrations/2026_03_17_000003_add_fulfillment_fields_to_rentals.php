<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            if (!Schema::hasColumn('rentals', 'fulfillment_method')) {
                $table->string('fulfillment_method')->nullable()->after('business_phone'); // pickup|delivery
            }
            if (!Schema::hasColumn('rentals', 'delivery_address')) {
                $table->text('delivery_address')->nullable()->after('fulfillment_method');
            }
            if (!Schema::hasColumn('rentals', 'return_method')) {
                $table->string('return_method')->nullable()->after('delivery_address'); // pickup_return|rider_return
            }
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            if (Schema::hasColumn('rentals', 'return_method')) {
                $table->dropColumn('return_method');
            }
            if (Schema::hasColumn('rentals', 'delivery_address')) {
                $table->dropColumn('delivery_address');
            }
            if (Schema::hasColumn('rentals', 'fulfillment_method')) {
                $table->dropColumn('fulfillment_method');
            }
        });
    }
};

