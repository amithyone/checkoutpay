<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('nigtax_consultant_settings')) {
            Schema::create('nigtax_consultant_settings', function (Blueprint $table) {
                $table->id();
                $table->string('consultant_name')->nullable();
                $table->string('firm_name')->nullable();
                $table->string('title')->nullable();
                $table->text('bio')->nullable();
                $table->string('license_number')->nullable();
                $table->string('contact_email')->nullable();
                $table->decimal('certified_fee_ngn', 15, 2)->default(0);
                $table->string('signature_image_path')->nullable();
                $table->string('stamp_image_path')->nullable();
                $table->boolean('is_enabled')->default(false);
                $table->unsignedBigInteger('signatures_applied_count')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('nigtax_certified_orders')) {
            Schema::create('nigtax_certified_orders', function (Blueprint $table) {
                $table->id();
                $table->string('customer_email');
                $table->string('customer_name')->nullable();
                $table->string('report_type', 32);
                $table->longText('report_snapshot_json')->nullable();
                $table->decimal('amount_paid', 15, 2);
                $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
                $table->string('transaction_id')->unique()->nullable();
                $table->string('status', 32)->default('awaiting_payment');
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('signed_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->string('signed_pdf_path')->nullable();
                $table->text('admin_notes')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
                $table->index('customer_email');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nigtax_certified_orders');
        Schema::dropIfExists('nigtax_consultant_settings');
    }
};
