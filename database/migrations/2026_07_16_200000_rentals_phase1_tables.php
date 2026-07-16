<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rental_vendor_applications')) {
            Schema::create('rental_vendor_applications', function (Blueprint $table) {
                $table->id();
                if (Schema::hasTable('renters')) {
                    $table->foreignId('renter_id')->constrained('renters')->cascadeOnDelete();
                } else {
                    $table->unsignedBigInteger('renter_id');
                }
                $table->string('business_name');
                $table->text('address');
                $table->string('phone', 30);
                $table->text('description')->nullable();
                $table->json('documents')->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->text('rejection_reason')->nullable();
                $table->timestamp('submitted_at')->useCurrent();
                $table->timestamp('reviewed_at')->nullable();
                if (Schema::hasTable('admins')) {
                    $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
                } else {
                    $table->unsignedBigInteger('reviewed_by')->nullable();
                }
                if (Schema::hasTable('businesses')) {
                    $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
                } else {
                    $table->unsignedBigInteger('business_id')->nullable();
                }
                $table->timestamps();

                $table->index(['renter_id', 'status']);
            });
        }

        if (! Schema::hasTable('rental_escrows') && Schema::hasTable('rentals')) {
            Schema::create('rental_escrows', function (Blueprint $table) {
                $table->id();
                $table->foreignId('rental_id')->unique()->constrained('rentals')->cascadeOnDelete();
            $table->enum('status', ['held', 'partially_released', 'released', 'refunded', 'frozen'])->default('held');
            $table->decimal('rent_held', 12, 2)->default(0);
            $table->decimal('deposit_held', 12, 2)->default(0);
            $table->decimal('rent_released', 12, 2)->default(0);
            $table->decimal('deposit_released', 12, 2)->default(0);
            $table->timestamp('rent_released_at')->nullable();
            $table->timestamp('deposit_released_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('rental_condition_reports') && Schema::hasTable('rentals')) {
            Schema::create('rental_condition_reports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('rental_id')->constrained('rentals')->cascadeOnDelete();
                if (Schema::hasTable('renters')) {
                    $table->foreignId('submitted_by_renter_id')->nullable()->constrained('renters')->nullOnDelete();
                } else {
                    $table->unsignedBigInteger('submitted_by_renter_id')->nullable();
                }
                if (Schema::hasTable('businesses')) {
                    $table->foreignId('submitted_by_business_id')->nullable()->constrained('businesses')->nullOnDelete();
                } else {
                    $table->unsignedBigInteger('submitted_by_business_id')->nullable();
                }
            $table->enum('phase', ['pickup', 'return']);
            $table->text('notes')->nullable();
            $table->json('images')->nullable();
            $table->timestamps();

                $table->index(['rental_id', 'phase']);
            });
        }

        if (! Schema::hasTable('rental_disputes') && Schema::hasTable('rentals')) {
            Schema::create('rental_disputes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('rental_id')->constrained('rentals')->cascadeOnDelete();
                if (Schema::hasTable('renters')) {
                    $table->foreignId('opened_by_renter_id')->nullable()->constrained('renters')->nullOnDelete();
                } else {
                    $table->unsignedBigInteger('opened_by_renter_id')->nullable();
                }
                if (Schema::hasTable('businesses')) {
                    $table->foreignId('opened_by_business_id')->nullable()->constrained('businesses')->nullOnDelete();
                } else {
                    $table->unsignedBigInteger('opened_by_business_id')->nullable();
                }
            $table->enum('reason', ['damage', 'missing', 'late', 'other']);
            $table->text('description');
            $table->decimal('requested_deposit_capture', 12, 2)->default(0);
            $table->enum('status', ['open', 'resolved'])->default('open');
            $table->enum('resolution', ['release_deposit', 'capture_partial', 'capture_full'])->nullable();
            $table->decimal('capture_amount', 12, 2)->nullable();
            $table->text('resolution_notes')->nullable();
                $table->timestamp('resolved_at')->nullable();
                if (Schema::hasTable('admins')) {
                    $table->foreignId('resolved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
                } else {
                    $table->unsignedBigInteger('resolved_by_admin_id')->nullable();
                }
                $table->timestamps();

                $table->index(['rental_id', 'status']);
            });
        }

        if (Schema::hasTable('rentals')) {
            Schema::table('rentals', function (Blueprint $table) {
                if (! Schema::hasColumn('rentals', 'is_walk_in')) {
                    $table->boolean('is_walk_in')->default(false)->after('status');
                }
                if (! Schema::hasColumn('rentals', 'walk_in_payment_note')) {
                    $table->string('walk_in_payment_note')->nullable()->after('is_walk_in');
                }
                if (! Schema::hasColumn('rentals', 'cancel_reason')) {
                    $table->text('cancel_reason')->nullable()->after('cancelled_at');
                }
                if (! Schema::hasColumn('rentals', 'penalty_amount')) {
                    $table->decimal('penalty_amount', 10, 2)->default(0)->after('deposit_amount');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $columns = ['is_walk_in', 'walk_in_payment_note', 'cancel_reason', 'penalty_amount'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('rentals', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('rental_disputes');
        Schema::dropIfExists('rental_condition_reports');
        Schema::dropIfExists('rental_escrows');
        Schema::dropIfExists('rental_vendor_applications');
    }
};
