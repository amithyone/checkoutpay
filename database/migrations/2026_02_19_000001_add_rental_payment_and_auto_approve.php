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
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('rental_id')->nullable()->after('business_website_id')->constrained('rentals')->onDelete('set null');
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->string('payment_link_code', 64)->nullable()->unique()->after('business_notes');
            $table->unsignedBigInteger('payment_id')->nullable()->after('payment_link_code');
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('set null');
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->boolean('rental_auto_approve')->default(false)->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['rental_id']);
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->dropColumn(['payment_link_code', 'payment_id']);
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('rental_auto_approve');
        });
    }
};
