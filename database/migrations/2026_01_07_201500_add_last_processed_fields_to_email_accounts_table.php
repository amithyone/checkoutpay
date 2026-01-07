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
            if (!Schema::hasColumn('email_accounts', 'last_processed_message_id')) {
                $table->string('last_processed_message_id')->nullable()->after('gmail_authorization_url')->comment('Last processed email message ID/UID for fast skipping');
            }
            if (!Schema::hasColumn('email_accounts', 'last_processed_at')) {
                $table->datetime('last_processed_at')->nullable()->after('last_processed_message_id')->comment('Last time emails were processed');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('email_accounts', 'last_processed_message_id')) {
                $table->dropColumn('last_processed_message_id');
            }
            if (Schema::hasColumn('email_accounts', 'last_processed_at')) {
                $table->dropColumn('last_processed_at');
            }
        });
    }
};
