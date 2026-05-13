<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('developer_program_partner_business_id')
                ->nullable()
                ->after('business_id');
            $table->foreign('developer_program_partner_business_id')
                ->references('id')
                ->on('businesses')
                ->nullOnDelete();
            $table->index('developer_program_partner_business_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['developer_program_partner_business_id']);
            $table->dropColumn('developer_program_partner_business_id');
        });
    }
};
