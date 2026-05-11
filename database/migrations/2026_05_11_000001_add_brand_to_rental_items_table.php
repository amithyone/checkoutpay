<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_items', function (Blueprint $table) {
            $table->string('brand', 120)->nullable()->after('name');
            $table->index(['category_id', 'brand']);
        });
    }

    public function down(): void
    {
        Schema::table('rental_items', function (Blueprint $table) {
            $table->dropIndex(['category_id', 'brand']);
            $table->dropColumn('brand');
        });
    }
};
