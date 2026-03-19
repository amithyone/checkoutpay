<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawal_requests', function (Blueprint $table) {
            $table->string('payout_provider')->nullable()->after('status');
            $table->string('payout_status')->nullable()->after('payout_provider');
            $table->string('payout_reference')->nullable()->after('payout_status');
            $table->string('payout_response_code')->nullable()->after('payout_reference');
            $table->string('payout_response_message')->nullable()->after('payout_response_code');
            $table->json('payout_raw_response')->nullable()->after('payout_response_message');
            $table->timestamp('payout_attempted_at')->nullable()->after('payout_raw_response');
            $table->timestamp('payout_failed_at')->nullable()->after('payout_attempted_at');
            $table->timestamp('payout_succeeded_at')->nullable()->after('payout_failed_at');

            $table->index(['payout_provider', 'payout_status']);
        });
    }

    public function down(): void
    {
        Schema::table('withdrawal_requests', function (Blueprint $table) {
            $table->dropIndex(['payout_provider', 'payout_status']);

            $table->dropColumn([
                'payout_provider',
                'payout_status',
                'payout_reference',
                'payout_response_code',
                'payout_response_message',
                'payout_raw_response',
                'payout_attempted_at',
                'payout_failed_at',
                'payout_succeeded_at',
            ]);
        });
    }
};

