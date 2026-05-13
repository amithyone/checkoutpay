<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumer_wallet_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_wallet_id')->constrained('whatsapp_wallets')->cascadeOnDelete();
            $table->string('sender', 16);
            $table->text('body');
            $table->timestamps();

            $table->index(['whatsapp_wallet_id', 'id'], 'ww_chat_wallet_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumer_wallet_chat_messages');
    }
};
