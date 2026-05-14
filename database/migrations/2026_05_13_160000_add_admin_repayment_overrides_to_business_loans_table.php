<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_loans', function (Blueprint $table) {
            $table->string('admin_repayment_type', 20)->nullable()->after('total_repayment');
            $table->string('admin_repayment_frequency', 20)->nullable()->after('admin_repayment_type');
        });
    }

    public function down(): void
    {
        Schema::table('business_loans', function (Blueprint $table) {
            $table->dropColumn(['admin_repayment_type', 'admin_repayment_frequency']);
        });
    }
};
