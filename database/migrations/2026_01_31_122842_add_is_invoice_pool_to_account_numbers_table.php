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
        Schema::table('account_numbers', function (Blueprint $table) {
            $table->boolean('is_invoice_pool')->default(false)->after('is_pool');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_numbers', function (Blueprint $table) {
            $table->dropColumn('is_invoice_pool');
        });
    }
};
