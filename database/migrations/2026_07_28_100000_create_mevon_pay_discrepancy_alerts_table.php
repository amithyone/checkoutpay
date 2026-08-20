<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mevon_pay_discrepancy_alerts', function (Blueprint $table) {
            $table->id();
            $table->timestamp('checked_at')->useCurrent();
            $table->decimal('expected_balance', 14, 2);
            $table->decimal('live_balance', 14, 2);
            $table->decimal('variance_amount', 14, 2);
            $table->decimal('tolerance', 14, 2);
            $table->foreignId('ledger_entry_id')->nullable()->constrained('mevon_pay_ledger_entries')->nullOnDelete();
            $table->string('trigger', 32);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('checked_at');
            $table->index('trigger');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mevon_pay_discrepancy_alerts');
    }
};
