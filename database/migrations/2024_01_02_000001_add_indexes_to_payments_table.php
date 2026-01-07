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
            // Composite index for common queries (status + date filtering)
            $table->index(['status', 'created_at'], 'idx_status_created_at');
            
            // Composite index for payment matching queries
            $table->index(['amount', 'payer_name', 'status'], 'idx_amount_payer_status');
            
            // Index for expiration queries
            $table->index(['status', 'created_at', 'updated_at'], 'idx_status_dates');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('idx_status_created_at');
            $table->dropIndex('idx_amount_payer_status');
            $table->dropIndex('idx_status_dates');
        });
    }
};
