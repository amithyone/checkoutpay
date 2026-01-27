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
        Schema::create('ticket_check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->onDelete('cascade');
            $table->foreignId('checked_in_by')->constrained('admins')->onDelete('cascade');
            $table->enum('check_in_method', ['qr_scan', 'manual'])->default('qr_scan');
            $table->string('location')->nullable(); // GPS or venue location
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('ticket_id');
            $table->index('checked_in_by');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_check_ins');
    }
};
