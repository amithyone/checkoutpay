<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rental_items') && ! Schema::hasColumn('rental_items', 'how_to_videos')) {
            Schema::table('rental_items', function (Blueprint $table) {
                $table->json('how_to_videos')->nullable()->after('specifications');
            });
        }

        if (! Schema::hasTable('rental_item_reviews')) {
            Schema::create('rental_item_reviews', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('rental_id');
                $table->unsignedBigInteger('rental_item_id');
                $table->unsignedBigInteger('renter_id');
                $table->unsignedTinyInteger('rating')->nullable();
                $table->string('condition', 16)->nullable();
                $table->text('missing_items')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->unique(['rental_id', 'rental_item_id', 'renter_id'], 'rental_item_reviews_unique');

                if (Schema::hasTable('rentals')) {
                    $table->foreign('rental_id')->references('id')->on('rentals')->cascadeOnDelete();
                }
                if (Schema::hasTable('rental_items')) {
                    $table->foreign('rental_item_id')->references('id')->on('rental_items')->cascadeOnDelete();
                }
                if (Schema::hasTable('renters')) {
                    $table->foreign('renter_id')->references('id')->on('renters')->cascadeOnDelete();
                }

                $table->index(['rental_item_id', 'created_at'], 'rental_item_reviews_item_created');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_item_reviews');

        if (Schema::hasTable('rental_items') && Schema::hasColumn('rental_items', 'how_to_videos')) {
            Schema::table('rental_items', function (Blueprint $table) {
                $table->dropColumn('how_to_videos');
            });
        }
    }
};
