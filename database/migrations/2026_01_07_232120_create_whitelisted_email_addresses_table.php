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
        if (Schema::hasTable('whitelisted_email_addresses')) {
            return; // Table already exists, skip migration
        }

        Schema::create('whitelisted_email_addresses', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique()->comment('Email address or domain to whitelist (e.g., alerts@gtbank.com or @gtbank.com)');
            $table->string('description')->nullable()->comment('Description of why this email is whitelisted');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('email');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whitelisted_email_addresses');
    }
};
