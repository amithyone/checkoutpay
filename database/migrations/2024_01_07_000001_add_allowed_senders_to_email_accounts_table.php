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
        Schema::table('email_accounts', function (Blueprint $table) {
            // Add field to store allowed sender email addresses/domains
            // JSON array: ["bank@example.com", "@gtbank.com", "alerts@accessbank.com"]
            if (!Schema::hasColumn('email_accounts', 'allowed_senders')) {
                $table->json('allowed_senders')->nullable()->after('notes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_accounts', function (Blueprint $table) {
            $table->dropColumn('allowed_senders');
        });
    }
};
