<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_facility_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('whatsapp_wallet_id')->constrained('whatsapp_wallets')->cascadeOnDelete();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->string('kind', 20); // overdraft | loan
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('NGN');
            $table->text('note')->nullable();
            $table->string('status', 20)->default('pending'); // pending | approved | rejected | cancelled
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['whatsapp_wallet_id', 'status']);
            $table->index(['kind', 'status']);
            $table->index(['business_id', 'status']);
        });

        if (Schema::hasTable('businesses') && ! Schema::hasColumn('businesses', 'overdraft_requested_amount')) {
            Schema::table('businesses', function (Blueprint $table) {
                $table->decimal('overdraft_requested_amount', 15, 2)->nullable()->after('overdraft_requested_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('businesses') && Schema::hasColumn('businesses', 'overdraft_requested_amount')) {
            Schema::table('businesses', function (Blueprint $table) {
                $table->dropColumn('overdraft_requested_amount');
            });
        }
        Schema::dropIfExists('credit_facility_requests');
    }
};
