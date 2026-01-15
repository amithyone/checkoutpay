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
        Schema::table('businesses', function (Blueprint $table) {
            // Profile picture
            $table->string('profile_picture')->nullable()->after('email');
            
            // Notification preferences
            $table->boolean('notifications_email_enabled')->default(true)->after('webhook_url');
            $table->boolean('notifications_payment_enabled')->default(true)->after('notifications_email_enabled');
            $table->boolean('notifications_withdrawal_enabled')->default(true)->after('notifications_payment_enabled');
            $table->boolean('notifications_website_enabled')->default(true)->after('notifications_withdrawal_enabled');
            $table->boolean('notifications_security_enabled')->default(true)->after('notifications_website_enabled');
            
            // Additional settings
            $table->string('timezone')->default('Africa/Lagos')->after('notifications_security_enabled');
            $table->string('currency', 3)->default('NGN')->after('timezone');
            $table->decimal('auto_withdraw_threshold', 15, 2)->nullable()->after('currency');
            $table->boolean('two_factor_enabled')->default(false)->after('auto_withdraw_threshold');
            $table->text('two_factor_secret')->nullable()->after('two_factor_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'profile_picture',
                'notifications_email_enabled',
                'notifications_payment_enabled',
                'notifications_withdrawal_enabled',
                'notifications_website_enabled',
                'notifications_security_enabled',
                'timezone',
                'currency',
                'auto_withdraw_threshold',
                'two_factor_enabled',
                'two_factor_secret',
            ]);
        });
    }
};
