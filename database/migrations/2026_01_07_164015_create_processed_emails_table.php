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
        if (!Schema::hasTable('processed_emails')) {
            Schema::create('processed_emails', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('email_account_id')->nullable();
                $table->string('message_id')->unique()->comment('Email message ID/UID for deduplication');
                $table->string('subject')->nullable();
                $table->string('from_email')->nullable();
                $table->text('from_name')->nullable();
                $table->text('text_body')->nullable();
                $table->text('html_body')->nullable();
                $table->datetime('email_date')->nullable();
                
                // Extracted payment info
                $table->decimal('amount', 15, 2)->nullable();
                $table->string('sender_name')->nullable();
                $table->string('account_number')->nullable();
                $table->text('extracted_data')->nullable()->comment('JSON of all extracted data');
                
                // Matching status
                $table->unsignedBigInteger('matched_payment_id')->nullable();
                $table->datetime('matched_at')->nullable();
                $table->boolean('is_matched')->default(false);
                
                // Processing metadata
                $table->text('processing_notes')->nullable();
                $table->timestamps();
                
                // Indexes for performance
                $table->index('email_account_id');
                $table->index('message_id');
                $table->index('is_matched');
                $table->index('matched_payment_id');
                $table->index('email_date');
                $table->index(['amount', 'sender_name', 'is_matched']);
                
                // Foreign keys
                $table->foreign('email_account_id')->references('id')->on('email_accounts')->onDelete('set null');
                $table->foreign('matched_payment_id')->references('id')->on('payments')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processed_emails');
    }
};
