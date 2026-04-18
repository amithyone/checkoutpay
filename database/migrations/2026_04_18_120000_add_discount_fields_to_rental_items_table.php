<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_items', function (Blueprint $table) {
            if (! Schema::hasColumn('rental_items', 'discount_active')) {
                $table->boolean('discount_active')->default(false)->after('is_featured');
            }
            if (! Schema::hasColumn('rental_items', 'discount_percent')) {
                $table->decimal('discount_percent', 5, 2)->nullable()->after('discount_active');
            }
            if (! Schema::hasColumn('rental_items', 'discount_starts_at')) {
                $table->timestamp('discount_starts_at')->nullable()->after('discount_percent');
            }
            if (! Schema::hasColumn('rental_items', 'discount_ends_at')) {
                $table->timestamp('discount_ends_at')->nullable()->after('discount_starts_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rental_items', function (Blueprint $table) {
            foreach (['discount_ends_at', 'discount_starts_at', 'discount_percent', 'discount_active'] as $col) {
                if (Schema::hasColumn('rental_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
