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
        Schema::table('payments', function (Blueprint $table) {
            // Check if columns already exist before adding
            if (!Schema::hasColumn('payments', 'webhook_sent_at')) {
                $table->timestamp('webhook_sent_at')->nullable()->after('matched_at');
            }
            if (!Schema::hasColumn('payments', 'webhook_status')) {
                $table->enum('webhook_status', ['pending', 'sent', 'failed', 'partial'])->default('pending')->after('webhook_sent_at');
            }
            if (!Schema::hasColumn('payments', 'webhook_attempts')) {
                $table->integer('webhook_attempts')->default(0)->after('webhook_status');
            }
            if (!Schema::hasColumn('payments', 'webhook_last_error')) {
                $table->text('webhook_last_error')->nullable()->after('webhook_attempts');
            }
            if (!Schema::hasColumn('payments', 'webhook_urls_sent')) {
                $table->json('webhook_urls_sent')->nullable()->after('webhook_last_error')->comment('Array of webhook URLs that were sent to');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'webhook_sent_at',
                'webhook_status',
                'webhook_attempts',
                'webhook_last_error',
                'webhook_urls_sent',
            ]);
        });
    }
};
