<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->string('issue_type', 64)->nullable()->after('channel');
            $table->unsignedBigInteger('payment_id')->nullable()->after('issue_type');
            $table->string('payment_transaction_id', 64)->nullable()->after('payment_id');
            $table->decimal('payment_amount_reported', 14, 2)->nullable()->after('payment_transaction_id');

            $table->index('payment_transaction_id');
            $table->index('issue_type');
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropIndex(['payment_transaction_id']);
            $table->dropIndex(['issue_type']);
            $table->dropColumn([
                'issue_type',
                'payment_id',
                'payment_transaction_id',
                'payment_amount_reported',
            ]);
        });
    }
};
