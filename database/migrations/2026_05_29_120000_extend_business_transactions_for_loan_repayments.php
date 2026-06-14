<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_transactions', function (Blueprint $table) {
            $table->foreignId('business_loan_ledger_entry_id')
                ->nullable()
                ->after('payment_id')
                ->constrained('business_loan_ledger_entries')
                ->nullOnDelete();
            $table->foreignId('counterparty_business_id')
                ->nullable()
                ->after('business_loan_ledger_entry_id')
                ->constrained('businesses')
                ->nullOnDelete();
            $table->string('reference', 64)->nullable()->after('type');
            $table->string('description', 500)->nullable()->after('reference');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE business_transactions MODIFY type VARCHAR(40) NOT NULL DEFAULT 'payment'");
        }

        Schema::table('business_transactions', function (Blueprint $table) {
            $table->unique(
                ['business_id', 'business_loan_ledger_entry_id'],
                'business_transactions_loan_ledger_unique'
            );
            $table->index(['business_id', 'type', 'transaction_date'], 'business_transactions_business_type_date');
        });
    }

    public function down(): void
    {
        Schema::table('business_transactions', function (Blueprint $table) {
            $table->dropUnique('business_transactions_loan_ledger_unique');
            $table->dropIndex('business_transactions_business_type_date');
            $table->dropConstrainedForeignId('business_loan_ledger_entry_id');
            $table->dropConstrainedForeignId('counterparty_business_id');
            $table->dropColumn(['reference', 'description']);
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE business_transactions MODIFY type ENUM('payment', 'withdrawal') NOT NULL DEFAULT 'payment'");
        }
    }
};
