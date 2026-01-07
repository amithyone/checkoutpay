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
        if (Schema::hasTable('bank_email_templates')) {
            return; // Table already exists, skip migration
        }

        Schema::create('bank_email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name'); // e.g., "GTBank", "Access Bank", etc.
            $table->string('sender_email')->nullable(); // Expected sender email (e.g., "alerts@gtbank.com")
            $table->string('sender_domain')->nullable(); // Expected sender domain (e.g., "@gtbank.com")
            $table->text('sample_html')->nullable(); // Sample HTML email for reference
            $table->text('sample_text')->nullable(); // Sample text email for reference
            
            // Extraction patterns/mappings
            $table->text('amount_pattern')->nullable(); // Regex pattern or XPath for amount extraction
            $table->text('sender_name_pattern')->nullable(); // Regex pattern or XPath for sender name extraction
            $table->text('account_number_pattern')->nullable(); // Regex pattern for account number extraction
            
            // Field locations (for HTML table structures)
            $table->string('amount_field_label')->nullable(); // e.g., "Amount", "Sum", "Value"
            $table->string('sender_name_field_label')->nullable(); // e.g., "Description", "From", "Sender"
            $table->string('account_number_field_label')->nullable(); // e.g., "Account Number", "Account"
            
            // Instructions/notes
            $table->text('extraction_notes')->nullable(); // Notes on how to extract data
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0); // Higher priority templates checked first
            $table->timestamps();

            $table->index('bank_name');
            $table->index('sender_email');
            $table->index('sender_domain');
            $table->index('is_active');
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_email_templates');
    }
};
