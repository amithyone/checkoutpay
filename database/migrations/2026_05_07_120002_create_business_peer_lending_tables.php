<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_lending_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lender_business_id')->constrained('businesses')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->decimal('interest_rate_percent', 8, 4);
            $table->unsignedInteger('term_days');
            $table->string('repayment_type', 20); // lump | split
            $table->string('status', 20)->default('pending_admin'); // pending_admin | active | paused | closed | rejected
            $table->string('public_slug', 40)->unique();
            $table->boolean('list_publicly')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('business_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_lending_offer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('borrower_business_id')->constrained('businesses')->cascadeOnDelete();
            $table->decimal('principal', 15, 2);
            $table->decimal('total_repayment', 15, 2);
            $table->string('status', 30)->default('pending_admin'); // pending_admin | active | repaid | rejected | defaulted
            $table->timestamp('disbursed_at')->nullable();
            $table->timestamp('repaid_at')->nullable();
            $table->text('borrower_message')->nullable();
            $table->timestamps();
        });

        Schema::create('business_loan_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_loan_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->timestamp('due_at');
            $table->decimal('amount_due', 15, 2);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->string('status', 20)->default('pending'); // pending | paid | overdue
            $table->timestamps();
            $table->unique(['business_loan_id', 'sequence']);
        });

        Schema::create('business_loan_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_loan_id')->constrained()->cascadeOnDelete();
            $table->string('entry_type', 40); // disbursement | collection | interest_adjustment
            $table->decimal('amount', 15, 2);
            $table->foreignId('from_business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->foreignId('to_business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_loan_ledger_entries');
        Schema::dropIfExists('business_loan_schedules');
        Schema::dropIfExists('business_loans');
        Schema::dropIfExists('business_lending_offers');
    }
};
