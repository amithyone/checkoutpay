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
        // Check if column already exists
        if (Schema::hasColumn('businesses', 'email_account_id')) {
            return; // Column already exists, skip migration
        }

        Schema::table('businesses', function (Blueprint $table) {
            $table->unsignedBigInteger('email_account_id')->nullable()->after('webhook_url');
            $table->index('email_account_id');
        });

        // Only add foreign key if email_accounts table exists
        if (Schema::hasTable('email_accounts')) {
            Schema::table('businesses', function (Blueprint $table) {
                $table->foreign('email_account_id')
                      ->references('id')
                      ->on('email_accounts')
                      ->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropForeign(['email_account_id']);
            $table->dropColumn('email_account_id');
        });
    }
};
