<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rental_favorites')) {
            return;
        }

        Schema::create('rental_favorites', function (Blueprint $table) {
            $table->id();
            if (Schema::hasTable('renters')) {
                $table->foreignId('renter_id')->constrained('renters')->cascadeOnDelete();
            } else {
                $table->unsignedBigInteger('renter_id');
            }
            if (Schema::hasTable('rental_items')) {
                $table->foreignId('rental_item_id')->constrained('rental_items')->cascadeOnDelete();
            } else {
                $table->unsignedBigInteger('rental_item_id');
            }
            $table->timestamps();

            $table->unique(['renter_id', 'rental_item_id']);
            $table->index('renter_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_favorites');
    }
};
