<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('charity_campaigns', 'end_date')) {
            Schema::table('charity_campaigns', function (Blueprint $table) {
                $table->date('end_date')->nullable();
            });
        }
        if (!Schema::hasColumn('charity_campaigns', 'currency')) {
            Schema::table('charity_campaigns', function (Blueprint $table) {
                $table->string('currency', 3)->default('NGN');
            });
        }
        if (!Schema::hasColumn('charity_campaigns', 'is_featured')) {
            Schema::table('charity_campaigns', function (Blueprint $table) {
                $table->boolean('is_featured')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('charity_campaigns', 'end_date')) {
            Schema::table('charity_campaigns', fn (Blueprint $table) => $table->dropColumn('end_date'));
        }
        if (Schema::hasColumn('charity_campaigns', 'currency')) {
            Schema::table('charity_campaigns', fn (Blueprint $table) => $table->dropColumn('currency'));
        }
        if (Schema::hasColumn('charity_campaigns', 'is_featured')) {
            Schema::table('charity_campaigns', fn (Blueprint $table) => $table->dropColumn('is_featured'));
        }
    }
};
