<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_wallets', function (Blueprint $table) {
            $table->id();
            $table->string('phone_e164', 32)->unique();
            $table->foreignId('renter_id')->nullable()->constrained('renters')->nullOnDelete();
            $table->decimal('balance', 14, 2)->default(0);
            $table->string('pin_hash')->nullable();
            $table->timestamp('pin_set_at')->nullable();
            $table->unsignedTinyInteger('pin_failed_attempts')->default(0);
            $table->timestamp('pin_locked_until')->nullable();
            $table->timestamp('kyc_verified_at')->nullable();
            $table->string('mevon_virtual_account_number', 64)->nullable();
            $table->string('mevon_bank_name')->nullable();
            $table->string('mevon_bank_code', 32)->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamps();

            $table->index(['status', 'phone_e164']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_wallets');
    }
};
