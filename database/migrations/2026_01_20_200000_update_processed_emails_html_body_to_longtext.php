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
        // Change html_body and text_body to longText to handle large emails
        Schema::table('processed_emails', function (Blueprint $table) {
            // Check if column exists and is not already longText
            if (Schema::hasColumn('processed_emails', 'html_body')) {
                // Get current column type
                $columnType = DB::select("SHOW COLUMNS FROM processed_emails WHERE Field = 'html_body'")[0]->Type ?? null;
                
                // Only change if it's not already longtext or longblob
                if ($columnType && !str_contains(strtolower($columnType), 'longtext') && !str_contains(strtolower($columnType), 'longblob')) {
                    DB::statement('ALTER TABLE processed_emails MODIFY html_body LONGTEXT');
                }
            }
            
            if (Schema::hasColumn('processed_emails', 'text_body')) {
                $columnType = DB::select("SHOW COLUMNS FROM processed_emails WHERE Field = 'text_body'")[0]->Type ?? null;
                
                if ($columnType && !str_contains(strtolower($columnType), 'longtext') && !str_contains(strtolower($columnType), 'longblob')) {
                    DB::statement('ALTER TABLE processed_emails MODIFY text_body LONGTEXT');
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to TEXT (optional - usually we don't need to revert this)
        Schema::table('processed_emails', function (Blueprint $table) {
            if (Schema::hasColumn('processed_emails', 'html_body')) {
                DB::statement('ALTER TABLE processed_emails MODIFY html_body TEXT');
            }
            
            if (Schema::hasColumn('processed_emails', 'text_body')) {
                DB::statement('ALTER TABLE processed_emails MODIFY text_body TEXT');
            }
        });
    }
};
