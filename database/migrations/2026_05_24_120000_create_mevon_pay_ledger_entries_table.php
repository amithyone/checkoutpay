<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mevon_pay_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->string('direction', 16);
            $table->string('flow_type', 64);
            $table->decimal('gross_amount', 14, 2);
            $table->unsignedInteger('mevon_inbound_fee')->nullable();
            $table->unsignedInteger('mevon_outbound_fee')->nullable();
            $table->decimal('net_mevon_impact', 14, 2);
            $table->string('external_reference')->nullable();
            $table->string('payout_reference')->nullable();
            $table->string('account_number', 32)->nullable();
            $table->nullableMorphs('source');
            $table->string('payout_api', 32)->nullable();
            $table->string('payout_bucket', 16)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['direction', 'occurred_at']);
            $table->index(['flow_type', 'occurred_at']);
            $table->index('payout_reference');
            $table->unique(['external_reference', 'direction'], 'mevon_ledger_ext_ref_direction_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mevon_pay_ledger_entries');
    }
};
