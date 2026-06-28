<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_save_together_pots', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('creator_wallet_id')->constrained('whatsapp_wallets')->cascadeOnDelete();
            $table->string('title', 120);
            $table->decimal('target_amount', 14, 2);
            $table->decimal('per_member_share', 14, 2);
            $table->decimal('total_contributed', 14, 2)->default(0);
            $table->string('completion_mode', 32);
            $table->timestamp('deadline_at')->nullable();
            $table->string('status', 24)->default('collecting');
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('currency', 8)->default('NGN');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['status', 'deadline_at'], 'wst_pot_stat_dead');
            $table->index(['creator_wallet_id', 'status'], 'wst_pot_creator_stat');
        });

        Schema::create('wallet_save_together_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pot_id')->constrained('wallet_save_together_pots')->cascadeOnDelete();
            $table->unsignedBigInteger('wallet_id')->nullable();
            $table->string('phone_e164', 32);
            $table->string('display_name', 128)->nullable();
            $table->string('role', 16)->default('member');
            $table->decimal('share_target', 14, 2);
            $table->decimal('contributed_amount', 14, 2)->default(0);
            $table->decimal('withdrawn_amount', 14, 2)->default(0);
            $table->string('status', 24)->default('invited');
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('first_contributed_at')->nullable();
            $table->timestamp('share_completed_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();

            $table->unique(['pot_id', 'phone_e164'], 'wst_member_pot_phone');
            $table->index(['phone_e164', 'status'], 'wst_member_phone_stat');
        });

        Schema::create('wallet_save_together_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pot_id')->constrained('wallet_save_together_pots')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('wallet_save_together_members')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('kind', 16);
            $table->unsignedBigInteger('whatsapp_wallet_transaction_id')->nullable();
            $table->timestamps();

            $table->index(['pot_id', 'kind'], 'wst_contrib_pot_kind');
            $table->index(['member_id', 'kind'], 'wst_contrib_member_kind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_save_together_contributions');
        Schema::dropIfExists('wallet_save_together_members');
        Schema::dropIfExists('wallet_save_together_pots');
    }
};
