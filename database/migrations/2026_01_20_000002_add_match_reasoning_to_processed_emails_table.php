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
        Schema::table('processed_emails', function (Blueprint $table) {
            // Add match reasoning fields
            if (!Schema::hasColumn('processed_emails', 'last_match_reason')) {
                $table->text('last_match_reason')->nullable()->after('processing_notes')->comment('Reason why email did not match (if unmatched)');
            }
            
            if (!Schema::hasColumn('processed_emails', 'match_attempts_count')) {
                $table->integer('match_attempts_count')->default(0)->after('last_match_reason')->comment('Number of times we attempted to match this email');
            }
            
            if (!Schema::hasColumn('processed_emails', 'extraction_method')) {
                $table->string('extraction_method')->nullable()->after('extracted_data')->comment('Method used to extract data: html_table, html_text, rendered_text, template');
            }
            
            // Add index for faster queries on unmatched emails (shortened name to avoid MySQL 64 char limit)
            $table->index(['is_matched', 'match_attempts_count', 'email_date'], 'pe_matched_count_date_idx');
            $table->index(['extraction_method', 'is_matched'], 'pe_ext_method_matched_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('processed_emails', function (Blueprint $table) {
            $table->dropIndex(['is_matched', 'match_attempts_count', 'email_date']);
            $table->dropIndex(['extraction_method', 'is_matched']);
            
            if (Schema::hasColumn('processed_emails', 'last_match_reason')) {
                $table->dropColumn('last_match_reason');
            }
            
            if (Schema::hasColumn('processed_emails', 'match_attempts_count')) {
                $table->dropColumn('match_attempts_count');
            }
            
            if (Schema::hasColumn('processed_emails', 'extraction_method')) {
                $table->dropColumn('extraction_method');
            }
        });
    }
};
