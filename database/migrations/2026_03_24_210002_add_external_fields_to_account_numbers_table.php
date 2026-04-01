<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_numbers', function (Blueprint $table) {
            if (!Schema::hasColumn('account_numbers', 'is_external')) {
                $table->boolean('is_external')->default(false)->after('is_tickets_pool');
            }
            if (!Schema::hasColumn('account_numbers', 'external_provider')) {
                $table->string('external_provider', 50)->nullable()->after('is_external');
                $table->index('external_provider');
            }
        });
    }

    public function down(): void
    {
        Schema::table('account_numbers', function (Blueprint $table) {
            if (Schema::hasColumn('account_numbers', 'external_provider')) {
                $table->dropIndex(['external_provider']);
                $table->dropColumn('external_provider');
            }
            if (Schema::hasColumn('account_numbers', 'is_external')) {
                $table->dropColumn('is_external');
            }
        });
    }
};
