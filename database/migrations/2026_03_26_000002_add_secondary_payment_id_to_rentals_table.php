<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            if (!Schema::hasColumn('rentals', 'secondary_payment_id')) {
                $table->unsignedBigInteger('secondary_payment_id')->nullable()->after('payment_id');
                $table->foreign('secondary_payment_id')->references('id')->on('payments')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            if (Schema::hasColumn('rentals', 'secondary_payment_id')) {
                $table->dropForeign(['secondary_payment_id']);
                $table->dropColumn('secondary_payment_id');
            }
        });
    }
};

