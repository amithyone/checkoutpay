<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rental_items')) {
            return;
        }

        Schema::table('rental_items', function (Blueprint $table) {
            if (! Schema::hasColumn('rental_items', 'featured_tag')) {
                $table->string('featured_tag')->nullable()->after('is_featured');
            }
            if (! Schema::hasColumn('rental_items', 'featured_sort')) {
                $table->unsignedSmallInteger('featured_sort')->nullable()->after('featured_tag');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('rental_items')) {
            return;
        }

        Schema::table('rental_items', function (Blueprint $table) {
            foreach (['featured_tag', 'featured_sort'] as $column) {
                if (Schema::hasColumn('rental_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
