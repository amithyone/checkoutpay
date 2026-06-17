<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_name_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 64)->unique();
            $table->foreignId('whatsapp_wallet_id')->constrained('whatsapp_wallets')->cascadeOnDelete();
            $table->string('reference', 32)->unique();
            $table->string('proposed_name', 200);
            $table->string('alternate_name', 200)->nullable();
            $table->string('owner_full_name', 160);
            $table->string('owner_phone', 32);
            $table->string('owner_email', 160);
            $table->text('business_address');
            $table->string('nature_of_business', 500);
            $table->string('id_type', 32);
            $table->string('id_document_path', 500);
            $table->string('status', 32)->default('paid');
            $table->unsignedTinyInteger('progress_percent')->default(15);
            $table->string('status_label', 120)->nullable();
            $table->decimal('fee_amount', 14, 2);
            $table->string('fee_currency', 8)->default('NGN');
            $table->foreignId('fee_transaction_id')->nullable()->constrained('whatsapp_wallet_transactions')->nullOnDelete();
            $table->string('rejected_reason', 500)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedSmallInteger('estimated_completion_hours_min')->nullable();
            $table->unsignedSmallInteger('estimated_completion_hours_max')->nullable();
            $table->string('approved_business_name', 200)->nullable();
            $table->string('business_account_number', 32)->nullable();
            $table->string('business_account_name', 200)->nullable();
            $table->string('business_bank_name', 120)->nullable();
            $table->string('business_bank_code', 16)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['whatsapp_wallet_id', 'status']);
            $table->index(['whatsapp_wallet_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_name_registrations');
    }
};
