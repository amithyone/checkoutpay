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
        Schema::create('renters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            
            // KYC Information (from account verification)
            $table->string('verified_account_number')->nullable();
            $table->string('verified_account_name')->nullable();
            $table->string('verified_bank_name')->nullable();
            $table->string('verified_bank_code')->nullable();
            $table->timestamp('kyc_verified_at')->nullable();
            
            // Status
            $table->boolean('is_active')->default(true);
            
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('email');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('renters');
    }
};
