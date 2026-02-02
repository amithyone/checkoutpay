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
        Schema::create('membership_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('membership_id');
            $table->unsignedBigInteger('payment_id')->nullable(); // Link to payment
            $table->string('member_name');
            $table->string('member_email');
            $table->string('member_phone')->nullable();
            $table->string('subscription_number')->unique(); // Unique subscription ID
            $table->date('start_date');
            $table->date('expires_at');
            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active');
            $table->text('qr_code_data')->nullable(); // QR code data for card
            $table->string('card_pdf_path')->nullable(); // Path to generated PDF card
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('membership_id')->references('id')->on('memberships')->onDelete('cascade');
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('set null');
            $table->index(['membership_id', 'status']);
            $table->index(['member_email']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('membership_subscriptions');
    }
};
