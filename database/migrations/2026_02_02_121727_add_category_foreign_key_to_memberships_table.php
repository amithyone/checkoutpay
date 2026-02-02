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
        // Only add foreign key if both tables exist
        if (Schema::hasTable('membership_categories') && Schema::hasTable('memberships')) {
            // Check if foreign key already exists
            $db = Schema::getConnection();
            $foreignKeys = $db->select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = ? 
                AND TABLE_NAME = 'memberships' 
                AND COLUMN_NAME = 'category_id' 
                AND REFERENCED_TABLE_NAME = 'membership_categories'
            ", [$db->getDatabaseName()]);
            
            if (empty($foreignKeys)) {
                Schema::table('memberships', function (Blueprint $table) {
                    $table->foreign('category_id')
                        ->references('id')
                        ->on('membership_categories')
                        ->onDelete('set null');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });
    }
};
