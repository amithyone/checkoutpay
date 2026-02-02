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
        Schema::create('event_coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->string('code')->unique();
            $table->string('discount_type')->default('percentage'); // percentage or fixed
            $table->decimal('discount_value', 10, 2);
            $table->integer('usage_limit')->nullable(); // null = unlimited
            $table->integer('used_count')->default(0);
            $table->dateTime('valid_from')->nullable();
            $table->dateTime('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['event_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_coupons');
    }
};
