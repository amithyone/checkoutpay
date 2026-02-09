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
        Schema::table('membership_subscriptions', function (Blueprint $table) {
            $table->timestamp('expiration_email_sent_at')->nullable()->after('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('membership_subscriptions', function (Blueprint $table) {
            $table->dropColumn('expiration_email_sent_at');
        });
    }
};
