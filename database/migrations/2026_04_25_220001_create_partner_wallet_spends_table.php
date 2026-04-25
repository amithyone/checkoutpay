<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Upgrades installs that ran the older tagine_partner_spends migration before it was replaced.
     */
    public function up(): void
    {
        Schema::dropIfExists('tagine_partner_spends');

        if (Schema::hasTable('partner_wallet_spends')) {
            return;
        }

        Schema::create('partner_wallet_spends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('idempotency_key', 80);
            $table->string('phone_e164', 32);
            $table->decimal('amount', 14, 2);
            $table->string('status', 20)->default('processing');
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->unsignedBigInteger('whatsapp_wallet_transaction_id')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        //
    }
};
