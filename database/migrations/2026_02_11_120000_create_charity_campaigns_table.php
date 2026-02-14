<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charity_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->onDelete('set null');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('story')->nullable();
            $table->decimal('goal_amount', 15, 2)->default(0);
            $table->decimal('raised_amount', 15, 2)->default(0);
            $table->string('image')->nullable();
            $table->string('currency', 3)->default('NGN');
            $table->date('end_date')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->boolean('is_featured')->default(false);
            $table->timestamps();

            $table->index('status');
            $table->index('business_id');
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charity_campaigns');
    }
};
