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
            $table->foreignId('ticket_order_id')->constrained('ticket_orders')->onDelete('cascade');
            $table->foreignId('ticket_type_id')->constrained('ticket_types')->onDelete('cascade');
            $table->string('ticket_number')->unique(); // e.g., "TKT-20260127-ABC123-001"
            $table->string('qr_code')->nullable(); // File path to QR code image
            $table->text('qr_code_data')->nullable(); // JSON data encoded in QR
            $table->string('verification_token')->unique(); // Security token for QR verification
            $table->enum('status', ['valid', 'used', 'cancelled', 'refunded'])->default('valid');
            $table->datetime('checked_in_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('admins')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index('ticket_order_id');
            $table->index('ticket_type_id');
            $table->index('ticket_number');
            $table->index('verification_token');
            $table->index('status');
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
