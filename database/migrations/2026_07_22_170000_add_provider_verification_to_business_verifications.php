<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_verifications')) {
            return;
        }

        Schema::table('business_verifications', function (Blueprint $table) {
            if (! Schema::hasColumn('business_verifications', 'provider_verified_at')) {
                $table->timestamp('provider_verified_at')->nullable()->after('reviewed_at');
            }
            if (! Schema::hasColumn('business_verifications', 'provider_verified_by')) {
                $table->unsignedBigInteger('provider_verified_by')->nullable()->after('provider_verified_at');
            }
            if (! Schema::hasColumn('business_verifications', 'provider_verified_name')) {
                $table->string('provider_verified_name')->nullable()->after('provider_verified_by');
            }
            if (! Schema::hasColumn('business_verifications', 'provider_verify_reference')) {
                $table->string('provider_verify_reference')->nullable()->after('provider_verified_name');
            }
            if (! Schema::hasColumn('business_verifications', 'provider_verify_status')) {
                $table->string('provider_verify_status', 20)->nullable()->after('provider_verify_reference');
            }
            if (! Schema::hasColumn('business_verifications', 'provider_verify_message')) {
                $table->text('provider_verify_message')->nullable()->after('provider_verify_status');
            }
            if (! Schema::hasColumn('business_verifications', 'provider_verify_payload')) {
                $table->json('provider_verify_payload')->nullable()->after('provider_verify_message');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('business_verifications')) {
            return;
        }

        Schema::table('business_verifications', function (Blueprint $table) {
            $table->dropColumn([
                'provider_verified_at',
                'provider_verified_by',
                'provider_verified_name',
                'provider_verify_reference',
                'provider_verify_status',
                'provider_verify_message',
                'provider_verify_payload',
            ]);
        });
    }
};
