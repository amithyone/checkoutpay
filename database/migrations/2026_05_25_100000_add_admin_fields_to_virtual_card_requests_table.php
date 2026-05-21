<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('virtual_card_requests', function (Blueprint $table) {
            $table->text('admin_notes')->nullable()->after('failure_reason');
            $table->timestamp('activated_at')->nullable()->after('admin_notes');
            $table->foreignId('handled_by_admin_id')->nullable()->after('activated_at')
                ->constrained('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('virtual_card_requests', function (Blueprint $table) {
            $table->dropForeign(['handled_by_admin_id']);
            $table->dropColumn(['admin_notes', 'activated_at', 'handled_by_admin_id']);
        });
    }
};
