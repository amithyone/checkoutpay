<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_savings_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_wallet_id')->constrained('whatsapp_wallets')->cascadeOnDelete();
            $table->boolean('spend_to_save_enabled')->default(false);
            $table->decimal('spend_to_save_percent', 5, 2)->default(10);
            $table->boolean('reminder_enabled')->default(false);
            $table->string('reminder_frequency', 20)->default('off');
            $table->unsignedTinyInteger('reminder_weekday')->nullable();
            $table->unsignedTinyInteger('reminder_hour_local')->nullable();
            $table->timestamp('last_reminder_sent_at')->nullable();
            $table->timestamps();

            $table->unique('whatsapp_wallet_id');
        });

        Schema::create('wallet_savings_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_wallet_id')->constrained('whatsapp_wallets')->cascadeOnDelete();
            $table->string('name', 120);
            $table->decimal('target_amount', 14, 2);
            $table->decimal('saved_amount', 14, 2)->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['whatsapp_wallet_id', 'status']);
        });

        Schema::create('wallet_savings_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_wallet_id')->constrained('whatsapp_wallets')->cascadeOnDelete();
            $table->foreignId('wallet_savings_goal_id')->nullable()->constrained('wallet_savings_goals')->nullOnDelete();
            $table->foreignId('source_transaction_id')->nullable()->constrained('whatsapp_wallet_transactions')->nullOnDelete();
            $table->string('source', 30);
            $table->decimal('amount', 14, 2);
            $table->decimal('interest_rate_percent', 5, 2);
            $table->decimal('interest_amount', 14, 2)->nullable();
            $table->timestamp('locked_at');
            $table->timestamp('matures_at');
            $table->timestamp('matured_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique('source_transaction_id');
            $table->index(['whatsapp_wallet_id', 'status', 'matures_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_savings_locks');
        Schema::dropIfExists('wallet_savings_goals');
        Schema::dropIfExists('wallet_savings_settings');
    }
};
