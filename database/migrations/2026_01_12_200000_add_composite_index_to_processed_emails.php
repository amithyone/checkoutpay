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
        // Add composite index for faster lookups by email_account_id + message_id
        // This is critical for O(1) duplicate checking
        try {
            Schema::table('processed_emails', function (Blueprint $table) {
                $table->index(['email_account_id', 'message_id'], 'processed_emails_email_account_id_message_id_index');
            });
        } catch (\Exception $e) {
            // Index might already exist, check with raw SQL
            $connection = Schema::getConnection();
            $indexExists = $connection->selectOne(
                "SELECT COUNT(*) as count FROM information_schema.statistics 
                 WHERE table_schema = ? 
                 AND table_name = 'processed_emails' 
                 AND index_name = 'processed_emails_email_account_id_message_id_index'",
                [$connection->getDatabaseName()]
            );
            
            if ($indexExists && $indexExists->count == 0) {
                // Index doesn't exist, try raw SQL
                $connection->statement(
                    "CREATE INDEX processed_emails_email_account_id_message_id_index 
                     ON processed_emails (email_account_id, message_id)"
                );
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('processed_emails', function (Blueprint $table) {
                $table->dropIndex('processed_emails_email_account_id_message_id_index');
            });
        } catch (\Exception $e) {
            // Index might not exist, try raw SQL
            $connection = Schema::getConnection();
            $connection->statement(
                "DROP INDEX IF EXISTS processed_emails_email_account_id_message_id_index ON processed_emails"
            );
        }
    }
};
