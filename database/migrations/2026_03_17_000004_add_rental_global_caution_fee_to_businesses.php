<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            if (!Schema::hasColumn('businesses', 'rental_global_caution_fee_enabled')) {
                $table->boolean('rental_global_caution_fee_enabled')->default(false)->after('address');
            }
            if (!Schema::hasColumn('businesses', 'rental_global_caution_fee_percent')) {
                $table->decimal('rental_global_caution_fee_percent', 5, 2)->default(0)->after('rental_global_caution_fee_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            if (Schema::hasColumn('businesses', 'rental_global_caution_fee_percent')) {
                $table->dropColumn('rental_global_caution_fee_percent');
            }
            if (Schema::hasColumn('businesses', 'rental_global_caution_fee_enabled')) {
                $table->dropColumn('rental_global_caution_fee_enabled');
            }
        });
    }
};

