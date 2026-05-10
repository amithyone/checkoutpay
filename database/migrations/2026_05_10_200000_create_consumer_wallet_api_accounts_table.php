<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumer_wallet_api_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('whatsapp_wallet_id');
            $table->string('phone_e164', 32)->unique();
            $table->timestamps();

            $table->unique('whatsapp_wallet_id');
            $table->index('whatsapp_wallet_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumer_wallet_api_accounts');
    }
};
