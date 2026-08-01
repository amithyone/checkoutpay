<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('support_tickets', 'support_queue')) {
                $table->string('support_queue', 20)->nullable()->after('issue_type');
                $table->index(['support_queue', 'status', 'last_message_at'], 'support_tickets_queue_status_activity');
            }
        });

        Schema::table('admins', function (Blueprint $table) {
            if (! Schema::hasColumn('admins', 'handles_wallet_support_in_app')) {
                $table->boolean('handles_wallet_support_in_app')->default(true)->after('notify_wallet_signup');
            }
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('support_tickets', 'support_queue')) {
                $table->dropIndex('support_tickets_queue_status_activity');
                $table->dropColumn('support_queue');
            }
        });

        Schema::table('admins', function (Blueprint $table) {
            if (Schema::hasColumn('admins', 'handles_wallet_support_in_app')) {
                $table->dropColumn('handles_wallet_support_in_app');
            }
        });
    }
};
