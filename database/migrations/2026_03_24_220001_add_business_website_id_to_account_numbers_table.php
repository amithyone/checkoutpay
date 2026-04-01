<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_numbers', function (Blueprint $table) {
            if (!Schema::hasColumn('account_numbers', 'business_website_id')) {
                $table->foreignId('business_website_id')
                    ->nullable()
                    ->after('business_id')
                    ->constrained('business_websites')
                    ->nullOnDelete();
                $table->index('business_website_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('account_numbers', function (Blueprint $table) {
            if (Schema::hasColumn('account_numbers', 'business_website_id')) {
                $table->dropForeign(['business_website_id']);
                $table->dropIndex(['business_website_id']);
                $table->dropColumn('business_website_id');
            }
        });
    }
};
