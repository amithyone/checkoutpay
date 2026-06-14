<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->char('checkout_pay_code', 6)->nullable()->unique()->after('external_reference');
            $table->timestamp('checkout_pay_code_expires_at')->nullable()->after('checkout_pay_code');
            $table->string('payment_method_used', 32)->nullable()->after('payment_source');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['checkout_pay_code']);
            $table->dropColumn(['checkout_pay_code', 'checkout_pay_code_expires_at', 'payment_method_used']);
        });
    }
};
