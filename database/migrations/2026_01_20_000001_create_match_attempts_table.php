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
        Schema::create('match_attempts', function (Blueprint $table) {
            $table->id();
            
            // Relationships
            $table->unsignedBigInteger('payment_id')->nullable()->index();
            $table->unsignedBigInteger('processed_email_id')->nullable()->index();
            $table->string('transaction_id')->nullable()->index();
            
            // Match result
            $table->enum('match_result', ['matched', 'unmatched', 'rejected', 'partial'])->index();
            $table->text('reason')->comment('Detailed reason for match result');
            
            // Payment details
            $table->decimal('payment_amount', 15, 2)->nullable();
            $table->string('payment_name')->nullable();
            $table->string('payment_account_number')->nullable();
            $table->datetime('payment_created_at')->nullable();
            
            // Extracted email details
            $table->decimal('extracted_amount', 15, 2)->nullable();
            $table->string('extracted_name')->nullable();
            $table->string('extracted_account_number')->nullable();
            $table->string('email_subject')->nullable();
            $table->string('email_from')->nullable();
            $table->datetime('email_date')->nullable();
            
            // Comparison metrics
            $table->decimal('amount_diff', 15, 2)->nullable()->comment('Difference between payment and extracted amount');
            $table->integer('name_similarity_percent')->nullable()->comment('Name similarity percentage (0-100)');
            $table->integer('time_diff_minutes')->nullable()->comment('Time difference in minutes between payment and email');
            
            // Extraction method used
            $table->string('extraction_method')->nullable()->comment('html_table, html_text, rendered_text, template, fallback');
            
            // Details (JSON - stores all comparison data)
            $table->json('details')->nullable()->comment('Full comparison details: expected vs received values');
            
            // HTML/text snippets for debugging (truncated to save space)
            $table->text('html_snippet')->nullable()->comment('Relevant HTML snippet (first 500 chars)');
            $table->text('text_snippet')->nullable()->comment('Relevant text snippet (first 500 chars)');
            
            // Manual review (for training/improvement)
            $table->enum('manual_review_status', ['pending', 'reviewed', 'correct', 'incorrect'])->default('pending')->index();
            $table->text('manual_review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable()->comment('Admin user ID who reviewed');
            
            // Performance tracking
            $table->float('processing_time_ms')->nullable()->comment('Time taken to process this attempt in milliseconds');
            
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade');
            $table->foreign('processed_email_id')->references('id')->on('processed_emails')->onDelete('cascade');
            
            // Composite indexes for common queries
            $table->index(['payment_id', 'match_result']);
            $table->index(['processed_email_id', 'match_result']);
            $table->index(['transaction_id', 'match_result']);
            $table->index(['match_result', 'created_at']);
            $table->index(['manual_review_status', 'created_at']);
            $table->index(['extraction_method', 'match_result']);
            
            // Full text index for searching reasons
            $table->index('reason', 'match_attempts_reason_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_attempts');
    }
};
