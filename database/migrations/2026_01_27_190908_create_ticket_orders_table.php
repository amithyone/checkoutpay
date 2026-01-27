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
        Schema::create('ticket_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->foreignId('business_id')->constrained('businesses')->onDelete('cascade');
            $table->string('order_number')->unique(); // e.g., "TKT-20260127-ABC123"
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone')->nullable();
            $table->decimal('total_amount', 10, 2);
            $table->decimal('commission_amount', 10, 2)->default(0.00);
            $table->foreignId('payment_id')->nullable()->constrained('payments')->onDelete('set null');
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->enum('status', ['pending', 'confirmed', 'cancelled'])->default('pending');
            $table->datetime('purchased_at')->nullable();
            $table->text('refund_reason')->nullable();
            $table->foreignId('refunded_by')->nullable()->constrained('admins')->onDelete('set null');
            $table->datetime('refunded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('event_id');
            $table->index('business_id');
            $table->index('payment_id');
            $table->index('order_number');
            $table->index('payment_status');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_orders');
    }
};
