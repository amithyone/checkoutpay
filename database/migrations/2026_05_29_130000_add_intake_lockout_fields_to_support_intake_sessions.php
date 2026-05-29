<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_intake_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('support_intake_sessions', 'wrong_account_attempts')) {
                $table->unsignedTinyInteger('wrong_account_attempts')->default(0)->after('account_in_platform');
            }
            if (! Schema::hasColumn('support_intake_sessions', 'locked_until')) {
                $table->timestamp('locked_until')->nullable()->after('wrong_account_attempts');
            }
            if (! Schema::hasColumn('support_intake_sessions', 'last_visitor_ip')) {
                $table->string('last_visitor_ip', 45)->nullable()->after('locked_until');
            }
        });
    }

    public function down(): void
    {
        Schema::table('support_intake_sessions', function (Blueprint $table) {
            foreach (['wrong_account_attempts', 'locked_until', 'last_visitor_ip'] as $col) {
                if (Schema::hasColumn('support_intake_sessions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
