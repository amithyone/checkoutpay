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
            if (!Schema::hasColumn('processed_emails', 'source')) {
                $table->string('source')->default('imap')->after('email_account_id')->comment('Source: webhook, imap, gmail_api');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('processed_emails', function (Blueprint $table) {
            if (Schema::hasColumn('processed_emails', 'source')) {
                $table->dropColumn('source');
            }
        });
    }
};
