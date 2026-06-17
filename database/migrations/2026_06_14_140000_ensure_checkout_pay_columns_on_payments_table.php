<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent guard for hosts that deployed code before 2026_05_29_120000 ran.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        if (! Schema::hasColumn('payments', 'checkout_pay_code')) {
            Schema::table('payments', function (Blueprint $table) {
                if (Schema::hasColumn('payments', 'external_reference')) {
                    $table->char('checkout_pay_code', 6)->nullable()->after('external_reference');
                } else {
                    $table->char('checkout_pay_code', 6)->nullable();
                }
            });
        }

        if (! Schema::hasColumn('payments', 'checkout_pay_code_expires_at')) {
            Schema::table('payments', function (Blueprint $table) {
                if (Schema::hasColumn('payments', 'checkout_pay_code')) {
                    $table->timestamp('checkout_pay_code_expires_at')->nullable()->after('checkout_pay_code');
                } else {
                    $table->timestamp('checkout_pay_code_expires_at')->nullable();
                }
            });
        }

        if (! Schema::hasColumn('payments', 'payment_method_used')) {
            Schema::table('payments', function (Blueprint $table) {
                if (Schema::hasColumn('payments', 'payment_source')) {
                    $table->string('payment_method_used', 32)->nullable()->after('payment_source');
                } else {
                    $table->string('payment_method_used', 32)->nullable();
                }
            });
        }

        $this->ensureCheckoutPayCodeUniqueIndex();
    }

    public function down(): void
    {
        // Non-destructive: earlier migration owns rollback of these columns.
    }

    private function ensureCheckoutPayCodeUniqueIndex(): void
    {
        if (! Schema::hasColumn('payments', 'checkout_pay_code')) {
            return;
        }

        $connection = Schema::getConnection();
        $indexes = $connection->select(
            'SHOW INDEX FROM payments WHERE Column_name = ? AND Non_unique = 0',
            ['checkout_pay_code']
        );

        if ($indexes !== []) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->unique('checkout_pay_code');
        });
    }
};
