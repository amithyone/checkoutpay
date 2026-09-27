<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_account_prefix_rules', function (Blueprint $table) {
            $table->dropUnique('bank_account_prefix_rules_prefix_unique');
            $table->unique(['prefix', 'bank_code'], 'bank_account_prefix_rules_prefix_bank_unique');
        });
    }

    public function down(): void
    {
        Schema::table('bank_account_prefix_rules', function (Blueprint $table) {
            $table->dropUnique('bank_account_prefix_rules_prefix_bank_unique');
            $table->unique('prefix', 'bank_account_prefix_rules_prefix_unique');
        });
    }
};
