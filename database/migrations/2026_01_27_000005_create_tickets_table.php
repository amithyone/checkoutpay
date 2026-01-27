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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('ticket_type_id');
            $table->unsignedBigInteger('event_id');
            $table->string('attendee_name');
            $table->string('attendee_email');
            $table->text('qr_code')->nullable();
            $table->enum('check_in_status', ['not_checked_in', 'checked_in', 'cancelled'])->default('not_checked_in');
            $table->dateTime('checked_in_at')->nullable();
            $table->unsignedBigInteger('checked_in_by')->nullable();
            $table->boolean('is_transferable')->default(false);
            $table->unsignedBigInteger('transferred_from_ticket_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('ticket_orders')->onDelete('cascade');
            $table->foreign('ticket_type_id')->references('id')->on('ticket_types')->onDelete('cascade');
            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
            $table->index('order_id');
            $table->index('ticket_type_id');
            $table->index('event_id');
            $table->index('ticket_number');
            $table->index('check_in_status');
            $table->index('attendee_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
