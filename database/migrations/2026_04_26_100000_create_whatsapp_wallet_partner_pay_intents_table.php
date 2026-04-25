<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_wallet_partner_pay_intents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('confirm_token', 64)->unique();
            $table->string('phone_e164', 32);
            $table->decimal('amount', 14, 2);
            $table->string('order_reference', 120);
            $table->text('order_summary');
            $table->string('payer_name', 120);
            $table->string('webhook_url', 512)->nullable();
            $table->string('client_idempotency_key', 80);
            $table->string('status', 24)->default('pending_pin');
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('failure_reason', 500)->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['business_id', 'client_idempotency_key'], 'wa_wlt_partner_pay_intent_biz_idem');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_wallet_partner_pay_intents');
    }
};
