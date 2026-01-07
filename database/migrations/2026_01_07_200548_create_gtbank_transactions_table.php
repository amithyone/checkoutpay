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
        if (Schema::hasTable('gtbank_transactions')) {
            return; // Table already exists, skip migration
        }

        Schema::create('gtbank_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('account_number');
            $table->decimal('amount', 15, 2);
            $table->string('sender_name')->nullable();
            $table->enum('transaction_type', ['CREDIT', 'DEBIT']);
            $table->date('value_date');
            $table->text('narration')->nullable();
            $table->string('bank_name')->default('Guaranty Trust Bank');
            $table->string('duplicate_hash')->unique()->comment('Hash of account_number + amount + value_date + narration for duplicate prevention');
            $table->unsignedBigInteger('processed_email_id')->nullable()->comment('Reference to processed_emails table');
            $table->unsignedBigInteger('bank_template_id')->nullable()->comment('Reference to bank_email_templates table');
            $table->timestamps();

            // Indexes
            $table->index('account_number');
            $table->index('value_date');
            $table->index('transaction_type');
            $table->index('processed_email_id');
            $table->index('bank_template_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gtbank_transactions');
    }
};
