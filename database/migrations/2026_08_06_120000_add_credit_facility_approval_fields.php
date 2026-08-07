<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_facility_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('credit_facility_requests', 'funder_business_id')) {
                $table->foreignId('funder_business_id')->nullable()->after('business_id')->constrained('businesses')->nullOnDelete();
            }
            if (! Schema::hasColumn('credit_facility_requests', 'approved_amount')) {
                $table->decimal('approved_amount', 15, 2)->nullable()->after('amount');
            }
            if (! Schema::hasColumn('credit_facility_requests', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('credit_facility_requests', 'approved_by_admin_id')) {
                $table->unsignedBigInteger('approved_by_admin_id')->nullable()->after('approved_at');
            }
            if (! Schema::hasColumn('credit_facility_requests', 'admin_notes')) {
                $table->text('admin_notes')->nullable()->after('note');
            }
        });

        if (Schema::hasTable('businesses') && ! Schema::hasColumn('businesses', 'overdraft_funder_business_id')) {
            Schema::table('businesses', function (Blueprint $table) {
                $table->foreignId('overdraft_funder_business_id')->nullable()->after('overdraft_funding_source')->constrained('businesses')->nullOnDelete();
            });
        }

        if (Schema::hasTable('businesses') && ! Schema::hasColumn('businesses', 'is_master_loan_account')) {
            Schema::table('businesses', function (Blueprint $table) {
                $table->boolean('is_master_loan_account')->default(false)->after('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('businesses') && Schema::hasColumn('businesses', 'is_master_loan_account')) {
            Schema::table('businesses', function (Blueprint $table) {
                $table->dropColumn('is_master_loan_account');
            });
        }
        if (Schema::hasTable('businesses') && Schema::hasColumn('businesses', 'overdraft_funder_business_id')) {
            Schema::table('businesses', function (Blueprint $table) {
                $table->dropConstrainedForeignId('overdraft_funder_business_id');
            });
        }
        Schema::table('credit_facility_requests', function (Blueprint $table) {
            foreach (['funder_business_id', 'approved_amount', 'approved_at', 'approved_by_admin_id', 'admin_notes'] as $col) {
                if (Schema::hasColumn('credit_facility_requests', $col)) {
                    if ($col === 'funder_business_id') {
                        $table->dropConstrainedForeignId('funder_business_id');
                    } else {
                        $table->dropColumn($col);
                    }
                }
            }
        });
    }
};
