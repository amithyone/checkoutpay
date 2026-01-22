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
            $table->string('webhook_url', 500)->nullable()->after('website_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_websites', function (Blueprint $table) {
            $table->dropColumn('webhook_url');
        });
    }
};
