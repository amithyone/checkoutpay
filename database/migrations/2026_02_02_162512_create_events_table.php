<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Base events table; view_count, event_type, and background_color are added by later migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('events')) {
            return;
        }

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->onDelete('cascade');
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('venue')->nullable();
            $table->text('address')->nullable();
            $table->dateTime('start_date');
            $table->dateTime('end_date')->nullable();
            $table->string('timezone')->nullable();
            $table->string('cover_image')->nullable();
            $table->unsignedInteger('max_attendees')->nullable();
            $table->unsignedInteger('max_tickets_per_customer')->nullable();
            $table->boolean('allow_refunds')->default(true);
            $table->text('refund_policy')->nullable();
            $table->decimal('commission_percentage', 10, 2)->default(0);
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->unique(['business_id', 'slug']);
            $table->index('business_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
