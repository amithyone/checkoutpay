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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->string('invoice_number')->unique();
            $table->enum('status', ['draft', 'sent', 'viewed', 'paid', 'overdue', 'cancelled'])->default('draft');
            
            // Client Information
            $table->string('client_name');
            $table->string('client_email');
            $table->string('client_phone')->nullable();
            $table->text('client_address')->nullable();
            $table->string('client_company')->nullable();
            $table->string('client_tax_id')->nullable();
            
            // Invoice Details
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->string('currency', 3)->default('NGN');
            
            // Financial Fields
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0)->comment('Tax percentage (e.g., 7.5 for 7.5%)');
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->enum('discount_type', ['fixed', 'percentage'])->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            
            // Additional Information
            $table->text('notes')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->string('reference_number')->nullable();
            
            // Payment Link Integration
            $table->string('payment_link_code')->nullable()->unique();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->onDelete('set null');
            $table->timestamp('paid_at')->nullable();
            $table->decimal('paid_amount', 15, 2)->nullable();
            
            // Email Tracking
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->integer('view_count')->default(0);
            $table->boolean('email_sent_to_sender')->default(false);
            $table->boolean('email_sent_to_receiver')->default(false);
            $table->boolean('payment_email_sent_to_sender')->default(false);
            $table->boolean('payment_email_sent_to_receiver')->default(false);
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('business_id');
            $table->index('status');
            $table->index('invoice_date');
            $table->index('due_date');
            $table->index('client_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
