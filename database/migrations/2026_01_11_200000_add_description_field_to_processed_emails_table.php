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
        Schema::table('processed_emails', function (Blueprint $table) {
            if (!Schema::hasColumn('processed_emails', 'description_field')) {
                $table->string('description_field', 50)->nullable()->after('account_number')->comment('The 43-digit description field value from GTBank emails (e.g., 900877121002100859959000020260111094651392)');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('processed_emails', function (Blueprint $table) {
            if (Schema::hasColumn('processed_emails', 'description_field')) {
                $table->dropColumn('description_field');
            }
        });
    }
};
