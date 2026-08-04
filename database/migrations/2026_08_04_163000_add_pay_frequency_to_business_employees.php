<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_employees', function (Blueprint $table) {
            if (! Schema::hasColumn('business_employees', 'pay_frequency')) {
                $table->string('pay_frequency', 20)->default('monthly')->after('monthly_salary_ngn');
            }
            if (! Schema::hasColumn('business_employees', 'pay_day_hint')) {
                $table->string('pay_day_hint', 40)->nullable()->after('pay_frequency');
            }
        });
    }

    public function down(): void
    {
        Schema::table('business_employees', function (Blueprint $table) {
            if (Schema::hasColumn('business_employees', 'pay_day_hint')) {
                $table->dropColumn('pay_day_hint');
            }
            if (Schema::hasColumn('business_employees', 'pay_frequency')) {
                $table->dropColumn('pay_frequency');
            }
        });
    }
};
