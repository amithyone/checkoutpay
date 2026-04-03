<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calculator CIT/PIT rows from TaxController (/v1/tax/business, /v1/tax/personal).
 * These tables were not created by earlier NigTax migrations (only altered if they existed).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nigtax_business_records')) {
            Schema::create('nigtax_business_records', function (Blueprint $table) {
                $table->id();
                $table->string('business_name');
                $table->string('phone_number', 64)->nullable();
                $table->string('rc_number', 64)->nullable();
                $table->string('tin_number', 64)->nullable();
                $table->string('email', 255)->nullable();
                $table->text('address')->nullable();
                $table->string('statement_filename', 512)->nullable();
                $table->decimal('total_inflows', 15, 2)->default(0);
                $table->decimal('total_outflows', 15, 2)->default(0);
                $table->decimal('declared_profit_perc', 10, 4)->default(0);
                $table->decimal('assessable_profit', 15, 2)->default(0);
                $table->decimal('cit_amount', 15, 2)->default(0);
                $table->decimal('dev_levy_amount', 15, 2)->default(0);
                $table->decimal('total_tax_due', 15, 2)->default(0);
                $table->boolean('is_small_company')->default(false);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (! Schema::hasTable('nigtax_personal_records')) {
            Schema::create('nigtax_personal_records', function (Blueprint $table) {
                $table->id();
                $table->string('individual_name');
                $table->string('email', 255)->nullable();
                $table->decimal('annual_income', 15, 2)->default(0);
                $table->decimal('pension', 15, 2)->default(0);
                $table->decimal('nhf', 15, 2)->default(0);
                $table->decimal('nhis', 15, 2)->default(0);
                $table->decimal('life_assurance', 15, 2)->default(0);
                $table->decimal('rent_relief', 15, 2)->default(0);
                $table->decimal('total_tax_due', 15, 2)->default(0);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nigtax_personal_records');
        Schema::dropIfExists('nigtax_business_records');
    }
};
