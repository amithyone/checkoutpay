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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->onDelete('cascade');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('venue');
            $table->string('address')->nullable();
            $table->datetime('start_date');
            $table->datetime('end_date');
            $table->string('timezone')->default('Africa/Lagos');
            $table->string('cover_image')->nullable();
            $table->enum('status', ['draft', 'published', 'cancelled', 'completed'])->default('draft');
            $table->integer('max_attendees')->nullable();
            $table->integer('max_tickets_per_customer')->nullable(); // Set by business or admin
            $table->boolean('allow_refunds')->default(true);
            $table->text('refund_policy')->nullable();
            $table->string('ticket_template')->nullable(); // Custom template file name
            $table->json('ticket_design_settings')->nullable(); // Colors, fonts, logo position, etc.
            $table->decimal('commission_percentage', 5, 2)->default(0.00); // Commission per sale
            $table->timestamps();
            $table->softDeletes();

            $table->index('business_id');
            $table->index('status');
            $table->index('start_date');
            $table->index('slug');
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
