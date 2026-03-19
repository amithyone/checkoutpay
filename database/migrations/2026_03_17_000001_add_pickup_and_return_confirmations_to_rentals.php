<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            if (!Schema::hasColumn('rentals', 'returned_at')) {
                $table->timestamp('returned_at')->nullable()->after('completed_at');
            }
            if (!Schema::hasColumn('rentals', 'renter_return_requested_at')) {
                $table->timestamp('renter_return_requested_at')->nullable()->after('returned_at');
            }
            if (!Schema::hasColumn('rentals', 'business_return_confirmed_at')) {
                $table->timestamp('business_return_confirmed_at')->nullable()->after('renter_return_requested_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            if (Schema::hasColumn('rentals', 'business_return_confirmed_at')) {
                $table->dropColumn('business_return_confirmed_at');
            }
            if (Schema::hasColumn('rentals', 'renter_return_requested_at')) {
                $table->dropColumn('renter_return_requested_at');
            }
            if (Schema::hasColumn('rentals', 'returned_at')) {
                $table->dropColumn('returned_at');
            }
        });
    }
};

