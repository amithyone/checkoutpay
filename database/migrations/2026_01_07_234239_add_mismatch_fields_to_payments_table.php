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
            if (!Schema::hasColumn('payments', 'is_mismatch')) {
                $table->boolean('is_mismatch')->default(false)->after('status')->comment('True if amount received differs from expected but within tolerance');
                $table->decimal('received_amount', 15, 2)->nullable()->after('is_mismatch')->comment('Actual amount received if different from expected');
                $table->text('mismatch_reason')->nullable()->after('received_amount')->comment('Reason for mismatch if applicable');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'is_mismatch')) {
                $table->dropColumn(['is_mismatch', 'received_amount', 'mismatch_reason']);
            }
        });
    }
};
