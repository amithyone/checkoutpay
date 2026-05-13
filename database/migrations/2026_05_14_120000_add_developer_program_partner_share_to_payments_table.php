<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('developer_program_partner_share_amount', 15, 2)
                ->nullable()
                ->after('developer_program_partner_business_id');
            $table->timestamp('developer_program_partner_share_credited_at')
                ->nullable()
                ->after('developer_program_partner_share_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'developer_program_partner_share_amount',
                'developer_program_partner_share_credited_at',
            ]);
        });
    }
};
