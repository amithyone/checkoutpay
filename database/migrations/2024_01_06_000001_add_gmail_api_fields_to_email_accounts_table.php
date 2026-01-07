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
            // Add method field: 'imap' or 'gmail_api'
            if (!Schema::hasColumn('email_accounts', 'method')) {
                $table->string('method')->default('imap')->after('email');
            }
            
            // Gmail API specific fields
            if (!Schema::hasColumn('email_accounts', 'gmail_credentials_path')) {
                $table->string('gmail_credentials_path')->nullable()->after('password');
            }
            
            if (!Schema::hasColumn('email_accounts', 'gmail_token_path')) {
                $table->string('gmail_token_path')->nullable()->after('gmail_credentials_path');
            }
            
            if (!Schema::hasColumn('email_accounts', 'gmail_authorized')) {
                $table->boolean('gmail_authorized')->default(false)->after('gmail_token_path');
            }
            
            if (!Schema::hasColumn('email_accounts', 'gmail_authorization_url')) {
                $table->text('gmail_authorization_url')->nullable()->after('gmail_authorized');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'method',
                'gmail_credentials_path',
                'gmail_token_path',
                'gmail_authorized',
                'gmail_authorization_url',
            ]);
        });
    }
};
