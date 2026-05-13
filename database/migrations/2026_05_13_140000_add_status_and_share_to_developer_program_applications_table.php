<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('developer_program_applications', function (Blueprint $table) {
            $table->string('status', 32)->default('pending')->after('community_preference');
            $table->decimal('partner_fee_share_percent', 5, 2)->nullable()->after('status');
            $table->text('admin_notes')->nullable()->after('partner_fee_share_percent');
            $table->timestamp('approved_at')->nullable()->after('admin_notes');
        });
    }

    public function down(): void
    {
        Schema::table('developer_program_applications', function (Blueprint $table) {
            $table->dropColumn(['status', 'partner_fee_share_percent', 'admin_notes', 'approved_at']);
        });
    }
};
