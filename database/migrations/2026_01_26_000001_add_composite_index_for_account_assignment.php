<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This index optimizes the query in AccountNumberService that finds
     * pending payments with account numbers to determine which accounts are in use.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Check if index already exists
            $indexExists = false;
            try {
                $indexes = \Illuminate\Support\Facades\DB::select("SHOW INDEX FROM payments WHERE Key_name = 'idx_status_account_expires'");
                $indexExists = !empty($indexes);
            } catch (\Exception $e) {
                // Table might not exist yet, continue
            }
            
            if (!$indexExists) {
                $table->index(['status', 'account_number', 'expires_at'], 'idx_status_account_expires');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('idx_status_account_expires');
        });
    }
};
