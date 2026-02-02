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
        if (Schema::hasTable('memberships')) {
            return;
        }

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('who_is_it_for')->nullable(); // Target audience description
            $table->json('who_is_it_for_suggestions')->nullable(); // Predefined suggestions
            $table->decimal('price', 15, 2);
            $table->string('currency', 3)->default('NGN');
            $table->enum('duration_type', ['days', 'weeks', 'months', 'years'])->default('months');
            $table->integer('duration_value')->default(1); // e.g., 1 month, 3 months, etc.
            $table->json('features')->nullable(); // Array of features/benefits
            $table->json('images')->nullable(); // Array of image paths
            $table->text('terms_and_conditions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('max_members')->nullable(); // Maximum number of members allowed
            $table->integer('current_members')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('membership_categories')->onDelete('set null');
            $table->index(['business_id', 'is_active']);
            $table->index(['category_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
