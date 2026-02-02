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
        Schema::create('rentals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('renter_id')->constrained('renters')->onDelete('cascade');
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->string('rental_number')->unique();
            
            // Rental period
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('days')->default(1);
            
            // Pricing
            $table->decimal('daily_rate', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('deposit_amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('NGN');
            
            // Status
            $table->enum('status', ['pending', 'approved', 'active', 'completed', 'cancelled', 'rejected'])->default('pending');
            
            // KYC Information (from account verification)
            $table->string('verified_account_number')->nullable();
            $table->string('verified_account_name')->nullable();
            $table->string('verified_bank_name')->nullable();
            $table->string('verified_bank_code')->nullable();
            
            // Contact information
            $table->string('renter_name');
            $table->string('renter_email');
            $table->string('renter_phone')->nullable();
            $table->text('renter_address')->nullable();
            
            // Business contact (displayed to renter)
            $table->string('business_phone')->nullable();
            
            // Notes
            $table->text('renter_notes')->nullable();
            $table->text('business_notes')->nullable();
            
            // Timestamps
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('renter_id');
            $table->index('business_id');
            $table->index('status');
            $table->index('start_date');
            $table->index('end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rentals');
    }
};
