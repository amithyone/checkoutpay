<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedTinyInteger('split_installments')->nullable()->after('allow_split_payment')->comment('Number of split payments (e.g. 3)');
            $table->json('split_percentages')->nullable()->after('split_installments')->comment('Percentage per installment, e.g. [50,30,20] must sum to 100');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['split_installments', 'split_percentages']);
        });
    }
};
