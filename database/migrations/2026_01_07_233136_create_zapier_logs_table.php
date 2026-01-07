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
        if (Schema::hasTable('zapier_logs')) {
            return; // Table already exists, skip migration
        }

        Schema::create('zapier_logs', function (Blueprint $table) {
            $table->id();
            $table->json('payload')->comment('Full payload received from Zapier');
            $table->string('sender_name')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('time_sent')->nullable();
            $table->text('email_content')->nullable()->comment('Email body/content from Zapier');
            $table->string('extracted_from_email')->nullable()->comment('Extracted sender email address');
            $table->string('status')->default('received')->comment('received, processed, matched, rejected, error');
            $table->text('status_message')->nullable();
            $table->unsignedBigInteger('processed_email_id')->nullable()->comment('Reference to processed_emails if email was stored');
            $table->unsignedBigInteger('payment_id')->nullable()->comment('Reference to payments if payment was matched');
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->text('error_details')->nullable()->comment('Error details if processing failed');
            $table->timestamps();

            $table->index('status');
            $table->index('extracted_from_email');
            $table->index('created_at');
            $table->index('processed_email_id');
            $table->index('payment_id');
            
            // Foreign keys
            $table->foreign('processed_email_id')->references('id')->on('processed_emails')->onDelete('set null');
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zapier_logs');
    }
};
