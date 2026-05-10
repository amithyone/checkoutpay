<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Core ticketing schema (was missing from repo; required before coupon/check-in migrations).
     */
    public function up(): void
    {
        if (! Schema::hasTable('ticket_types')) {
            Schema::create('ticket_types', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->decimal('price', 12, 2);
                $table->unsignedInteger('quantity_available')->default(0);
                $table->unsignedInteger('quantity_sold')->default(0);
                $table->dateTime('sales_start_date')->nullable();
                $table->dateTime('sales_end_date')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['event_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('ticket_orders')) {
            Schema::create('ticket_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('order_number')->unique();
                $table->string('customer_name');
                $table->string('customer_email');
                $table->string('customer_phone')->nullable();
                $table->decimal('total_amount', 12, 2);
                $table->decimal('commission_amount', 12, 2)->default(0);
                $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
                $table->string('payment_status')->default('pending');
                $table->string('status')->default('pending');
                $table->timestamp('purchased_at')->nullable();
                $table->text('refund_reason')->nullable();
                $table->foreignId('refunded_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->timestamp('refunded_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['event_id', 'payment_status']);
                $table->index('business_id');
            });
        }

        if (! Schema::hasTable('tickets')) {
            Schema::create('tickets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_order_id')->constrained('ticket_orders')->cascadeOnDelete();
                $table->foreignId('ticket_type_id')->constrained('ticket_types')->cascadeOnDelete();
                $table->string('ticket_number');
                $table->string('qr_code')->nullable();
                $table->json('qr_code_data')->nullable();
                $table->string('verification_token')->nullable();
                $table->string('status')->default('valid');
                $table->timestamp('checked_in_at')->nullable();
                $table->foreignId('checked_in_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['ticket_order_id', 'status']);
                $table->index('ticket_type_id');
                $table->unique('verification_token');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('ticket_orders');
        Schema::dropIfExists('ticket_types');
    }
};
