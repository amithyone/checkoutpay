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
        if (Schema::hasTable('email_accounts')) {
            return; // Table already exists, skip migration
        }

        Schema::create('email_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Display name for the email account
            $table->string('email')->unique(); // Email address
            $table->string('host')->default('imap.gmail.com'); // IMAP host
            $table->integer('port')->default(993); // IMAP port
            $table->string('encryption')->default('ssl'); // ssl or tls
            $table->boolean('validate_cert')->default(false); // Validate SSL certificate
            $table->text('password'); // Encrypted password/app password
            $table->string('folder')->default('INBOX'); // Folder to monitor
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable(); // Additional notes
            $table->timestamps();
            $table->softDeletes();

            $table->index('email');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_accounts');
    }
};
