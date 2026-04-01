<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('renters', function (Blueprint $table) {
            if (! Schema::hasColumn('renters', 'rubies_account_number')) {
                $table->string('rubies_account_number')->nullable()->after('verified_bank_code');
            }
            if (! Schema::hasColumn('renters', 'rubies_account_name')) {
                $table->string('rubies_account_name')->nullable()->after('rubies_account_number');
            }
            if (! Schema::hasColumn('renters', 'rubies_bank_name')) {
                $table->string('rubies_bank_name')->nullable()->after('rubies_account_name');
            }
            if (! Schema::hasColumn('renters', 'rubies_bank_code')) {
                $table->string('rubies_bank_code')->nullable()->after('rubies_bank_name');
            }
            if (! Schema::hasColumn('renters', 'rubies_reference')) {
                $table->string('rubies_reference')->nullable()->after('rubies_bank_code');
            }
            if (! Schema::hasColumn('renters', 'rubies_account_created_at')) {
                $table->timestamp('rubies_account_created_at')->nullable()->after('rubies_reference');
            }
        });
    }

    public function down(): void
    {
        Schema::table('renters', function (Blueprint $table) {
            foreach ([
                'rubies_account_created_at',
                'rubies_reference',
                'rubies_bank_code',
                'rubies_bank_name',
                'rubies_account_name',
                'rubies_account_number',
            ] as $column) {
                if (Schema::hasColumn('renters', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

