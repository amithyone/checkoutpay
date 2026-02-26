<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->decimal('overdraft_limit', 15, 2)->default(0)->after('balance');
            $table->timestamp('overdraft_approved_at')->nullable()->after('overdraft_limit');
            $table->unsignedBigInteger('overdraft_approved_by')->nullable()->after('overdraft_approved_at');
            $table->timestamp('overdraft_interest_last_charged_at')->nullable()->after('overdraft_approved_by');
            $table->string('overdraft_status', 20)->nullable()->after('overdraft_interest_last_charged_at'); // pending, approved, rejected
            $table->timestamp('overdraft_requested_at')->nullable()->after('overdraft_status');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'overdraft_limit',
                'overdraft_approved_at',
                'overdraft_approved_by',
                'overdraft_interest_last_charged_at',
                'overdraft_status',
                'overdraft_requested_at',
            ]);
        });
    }
};
