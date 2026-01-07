<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id')->unique();
            $table->decimal('amount', 15, 2);
            $table->string('payer_name')->nullable();
            $table->string('bank')->nullable();
            $table->text('webhook_url');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->json('email_data')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('transaction_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
