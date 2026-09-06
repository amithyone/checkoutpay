<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->char('currency', 3)->default('NGN');
            $table->string('reuse_mode', 20)->default('one_time');
            $table->string('status', 20)->default('active');
            $table->string('code', 16)->unique();
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->decimal('collected_amount', 15, 2)->default(0);
            $table->unsignedInteger('collected_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'status']);
        });

        Schema::create('payment_link_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_link_id')->constrained('payment_links')->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamp('counted_at')->nullable();
            $table->timestamps();

            $table->unique('payment_id');
            $table->index('payment_link_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_link_payments');
        Schema::dropIfExists('payment_links');
    }
};
