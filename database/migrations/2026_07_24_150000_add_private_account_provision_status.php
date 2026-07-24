<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('businesses')) {
            Schema::table('businesses', function (Blueprint $table) {
                if (! Schema::hasColumn('businesses', 'rubies_account_provision_status')) {
                    $table->string('rubies_account_provision_status', 32)->nullable()->after('rubies_business_account_created_at');
                }
                if (! Schema::hasColumn('businesses', 'rubies_account_provision_error')) {
                    $table->text('rubies_account_provision_error')->nullable()->after('rubies_account_provision_status');
                }
                if (! Schema::hasColumn('businesses', 'rubies_account_provision_queued_at')) {
                    $table->timestamp('rubies_account_provision_queued_at')->nullable()->after('rubies_account_provision_error');
                }
            });
        }

        if (Schema::hasTable('whatsapp_wallets')) {
            Schema::table('whatsapp_wallets', function (Blueprint $table) {
                if (! Schema::hasColumn('whatsapp_wallets', 'private_account_provision_status')) {
                    $table->string('private_account_provision_status', 32)->nullable()->after('tier2_provisioned_at');
                }
                if (! Schema::hasColumn('whatsapp_wallets', 'private_account_provision_error')) {
                    $table->text('private_account_provision_error')->nullable()->after('private_account_provision_status');
                }
                if (! Schema::hasColumn('whatsapp_wallets', 'private_account_provision_queued_at')) {
                    $table->timestamp('private_account_provision_queued_at')->nullable()->after('private_account_provision_error');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('businesses')) {
            Schema::table('businesses', function (Blueprint $table) {
                $table->dropColumn([
                    'rubies_account_provision_status',
                    'rubies_account_provision_error',
                    'rubies_account_provision_queued_at',
                ]);
            });
        }

        if (Schema::hasTable('whatsapp_wallets')) {
            Schema::table('whatsapp_wallets', function (Blueprint $table) {
                $table->dropColumn([
                    'private_account_provision_status',
                    'private_account_provision_error',
                    'private_account_provision_queued_at',
                ]);
            });
        }
    }
};
