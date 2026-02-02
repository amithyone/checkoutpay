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
        Schema::create('rental_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained('rental_categories')->onDelete('cascade');
            $table->string('name');
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->text('address')->nullable();
            
            // Pricing
            $table->decimal('daily_rate', 10, 2)->default(0);
            $table->decimal('weekly_rate', 10, 2)->nullable();
            $table->decimal('monthly_rate', 10, 2)->nullable();
            $table->string('currency', 3)->default('NGN');
            
            // Availability
            $table->integer('quantity_available')->default(1);
            $table->boolean('is_available')->default(true);
            
            // Images
            $table->json('images')->nullable(); // Array of image paths
            
            // Additional details
            $table->json('specifications')->nullable(); // JSON for item specs
            $table->text('terms_and_conditions')->nullable();
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('business_id');
            $table->index('category_id');
            $table->index('city');
            $table->index('is_available');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rental_items');
    }
};
