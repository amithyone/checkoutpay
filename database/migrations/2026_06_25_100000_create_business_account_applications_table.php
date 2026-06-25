<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_account_applications', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 64)->unique();
            $table->foreignId('whatsapp_wallet_id')->constrained('whatsapp_wallets')->cascadeOnDelete();
            $table->string('reference', 32)->unique();
            $table->string('account_plan', 32);
            $table->json('service_categories')->nullable();
            $table->string('business_name', 200);
            $table->string('email', 160);
            $table->string('phone', 32)->nullable();
            $table->text('address')->nullable();
            $table->string('website_url', 500)->nullable();
            $table->string('cac_document_path', 500)->nullable();
            $table->string('status', 32)->default('submitted');
            $table->unsignedTinyInteger('progress_percent')->default(20);
            $table->string('status_label', 120)->nullable();
            $table->decimal('fee_amount', 14, 2)->default(0);
            $table->string('fee_currency', 8)->default('NGN');
            $table->foreignId('fee_transaction_id')->nullable()->constrained('whatsapp_wallet_transactions')->nullOnDelete();
            $table->foreignId('linked_business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->string('rejected_reason', 500)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('password_set_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['whatsapp_wallet_id', 'status'], 'baa_wallet_status_idx');
            $table->index(['whatsapp_wallet_id', 'created_at'], 'baa_wallet_created_idx');
            $table->index('email', 'baa_email_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_account_applications');
    }
};
