<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->boolean('overdraft_eligible')->default(false)->after('overdraft_requested_at');
            $table->string('overdraft_repayment_mode', 20)->nullable()->after('overdraft_eligible'); // single | split_30d
            $table->text('overdraft_application_notes')->nullable()->after('overdraft_repayment_mode');
            $table->string('overdraft_funding_source', 32)->default('platform')->after('overdraft_application_notes');
            $table->text('overdraft_approval_notes')->nullable()->after('overdraft_funding_source');
            $table->timestamp('overdraft_repayment_started_at')->nullable()->after('overdraft_approval_notes');
            $table->boolean('peer_lending_lend_eligible')->default(false)->after('overdraft_repayment_started_at');
            $table->boolean('peer_lending_borrow_eligible')->default(false)->after('peer_lending_lend_eligible');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'overdraft_eligible',
                'overdraft_repayment_mode',
                'overdraft_application_notes',
                'overdraft_funding_source',
                'overdraft_approval_notes',
                'overdraft_repayment_started_at',
                'peer_lending_lend_eligible',
                'peer_lending_borrow_eligible',
            ]);
        });
    }
};
