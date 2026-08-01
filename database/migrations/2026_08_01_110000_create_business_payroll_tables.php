<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->enum('payment_method', ['bank', 'wallet'])->default('bank');
            $table->string('phone_e164', 20)->nullable();
            $table->string('bank_code', 20)->nullable();
            $table->string('account_number', 20)->nullable();
            $table->string('account_name')->nullable();
            $table->decimal('monthly_salary_ngn', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'is_active']);
        });

        Schema::create('business_salary_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->enum('cadence', ['weekly', 'biweekly', 'monthly'])->default('weekly');
            $table->decimal('total_monthly_amount_ngn', 15, 2);
            $table->unsignedTinyInteger('installment_count')->default(4);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->enum('status', ['active', 'paused', 'completed', 'cancelled'])->default('active');
            $table->json('employee_ids')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
        });

        Schema::create('business_disbursement_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->enum('kind', ['bulk', 'scheduled'])->default('bulk');
            $table->enum('status', ['pending', 'processing', 'completed', 'partial_failed', 'failed', 'cancelled'])->default('pending');
            $table->decimal('total_amount_ngn', 15, 2)->default(0);
            $table->unsignedInteger('item_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->string('created_by_type')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->foreignId('salary_schedule_id')->nullable()->constrained('business_salary_schedules')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
        });

        Schema::create('business_disbursement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('business_disbursement_batches')->cascadeOnDelete();
            $table->foreignId('business_employee_id')->nullable()->constrained('business_employees')->nullOnDelete();
            $table->string('recipient_name');
            $table->enum('payment_method', ['bank', 'wallet']);
            $table->string('phone_e164', 20)->nullable();
            $table->string('bank_code', 20)->nullable();
            $table->string('account_number', 20)->nullable();
            $table->decimal('amount_ngn', 15, 2);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'skipped'])->default('pending');
            $table->string('idempotency_key', 64)->unique();
            $table->unsignedBigInteger('wallet_transaction_id')->nullable();
            $table->string('provider_reference')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'status']);
            $table->index(['due_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_disbursement_items');
        Schema::dropIfExists('business_disbursement_batches');
        Schema::dropIfExists('business_salary_schedules');
        Schema::dropIfExists('business_employees');
    }
};
