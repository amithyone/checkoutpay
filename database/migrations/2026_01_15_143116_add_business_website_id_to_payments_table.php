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
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('business_website_id')->nullable()->after('business_id')
                ->constrained('business_websites')->onDelete('set null');
            $table->index('business_website_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['business_website_id']);
            $table->dropIndex(['business_website_id']);
            $table->dropColumn('business_website_id');
        });
    }
};
