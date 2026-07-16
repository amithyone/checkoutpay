<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rental_featured_banners')) {
            return;
        }

        Schema::create('rental_featured_banners', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('tag')->nullable();
            $table->string('subtitle')->nullable();
            $table->string('image');
            $table->string('link_url')->nullable();
            if (Schema::hasTable('rental_items')) {
                $table->foreignId('rental_item_id')->nullable()->constrained('rental_items')->nullOnDelete();
            } else {
                $table->unsignedBigInteger('rental_item_id')->nullable();
            }
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            if (Schema::hasTable('admins')) {
                $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            } else {
                $table->unsignedBigInteger('created_by')->nullable();
            }
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_featured_banners');
    }
};
