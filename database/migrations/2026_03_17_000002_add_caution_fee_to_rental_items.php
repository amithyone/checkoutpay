<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_items', function (Blueprint $table) {
            if (!Schema::hasColumn('rental_items', 'caution_fee_enabled')) {
                $table->boolean('caution_fee_enabled')->default(false)->after('currency');
            }
            if (!Schema::hasColumn('rental_items', 'caution_fee_percent')) {
                // percentage (0-100). Stored as decimal for precision.
                $table->decimal('caution_fee_percent', 5, 2)->default(0)->after('caution_fee_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rental_items', function (Blueprint $table) {
            if (Schema::hasColumn('rental_items', 'caution_fee_percent')) {
                $table->dropColumn('caution_fee_percent');
            }
            if (Schema::hasColumn('rental_items', 'caution_fee_enabled')) {
                $table->dropColumn('caution_fee_enabled');
            }
        });
    }
};

