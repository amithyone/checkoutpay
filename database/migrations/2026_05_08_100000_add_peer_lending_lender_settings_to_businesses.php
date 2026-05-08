<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->decimal('peer_lending_lender_max_offer_amount', 15, 2)->nullable()->after('peer_lending_borrow_eligible');
            $table->decimal('peer_lending_lender_max_interest_percent', 8, 4)->nullable()->after('peer_lending_lender_max_offer_amount');
            $table->unsignedSmallInteger('peer_lending_lender_min_term_days')->nullable()->after('peer_lending_lender_max_interest_percent');
            $table->unsignedSmallInteger('peer_lending_lender_max_term_days')->nullable()->after('peer_lending_lender_min_term_days');
            $table->decimal('peer_lending_lender_min_balance_reserve', 15, 2)->nullable()->after('peer_lending_lender_max_term_days');
            $table->text('peer_lending_lender_conditions')->nullable()->after('peer_lending_lender_min_balance_reserve');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'peer_lending_lender_max_offer_amount',
                'peer_lending_lender_max_interest_percent',
                'peer_lending_lender_min_term_days',
                'peer_lending_lender_max_term_days',
                'peer_lending_lender_min_balance_reserve',
                'peer_lending_lender_conditions',
            ]);
        });
    }
};
