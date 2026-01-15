<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Check if column exists before adding
            if (!Schema::hasColumn('payments', 'business_website_id')) {
                $table->foreignId('business_website_id')->nullable()->after('business_id')
                    ->constrained('business_websites')->onDelete('set null');
                $table->index('business_website_id');
            } else {
                // Column exists, just add foreign key if missing
                $foreignKeys = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'business_website_id' AND REFERENCED_TABLE_NAME IS NOT NULL");
                if (empty($foreignKeys)) {
                    $table->foreign('business_website_id')->references('id')->on('business_websites')->onDelete('set null');
                }
                // Add index if missing
                $indexes = DB::select("SHOW INDEX FROM payments WHERE Column_name = 'business_website_id'");
                if (empty($indexes)) {
                    $table->index('business_website_id');
                }
            }
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
