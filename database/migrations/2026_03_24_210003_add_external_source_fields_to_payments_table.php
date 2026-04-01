<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'payment_source')) {
                $table->string('payment_source', 30)->default('internal')->after('status');
                $table->index('payment_source');
            }
            if (!Schema::hasColumn('payments', 'external_reference')) {
                $table->string('external_reference')->nullable()->after('payment_source');
                $table->index('external_reference');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'external_reference')) {
                $table->dropIndex(['external_reference']);
                $table->dropColumn('external_reference');
            }
            if (Schema::hasColumn('payments', 'payment_source')) {
                $table->dropIndex(['payment_source']);
                $table->dropColumn('payment_source');
            }
        });
    }
};
