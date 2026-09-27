<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_wallets', 'kyc_status')) {
                $table->string('kyc_status', 32)->nullable();
            }
            if (! Schema::hasColumn('whatsapp_wallets', 'kyc_tier')) {
                $table->unsignedTinyInteger('kyc_tier')->nullable();
            }
            if (! Schema::hasColumn('whatsapp_wallets', 'kyc_nationality')) {
                $table->string('kyc_nationality', 8)->nullable();
            }
            if (! Schema::hasColumn('whatsapp_wallets', 'kyc_occupation')) {
                $table->string('kyc_occupation', 191)->nullable();
            }
            if (! Schema::hasColumn('whatsapp_wallets', 'kyc_employer')) {
                $table->string('kyc_employer', 191)->nullable();
            }
            if (! Schema::hasColumn('whatsapp_wallets', 'kyc_purpose_of_account')) {
                $table->string('kyc_purpose_of_account', 255)->nullable();
            }
            if (! Schema::hasColumn('whatsapp_wallets', 'kyc_source_of_funds')) {
                $table->string('kyc_source_of_funds', 255)->nullable();
            }
            if (! Schema::hasColumn('whatsapp_wallets', 'kyc_residential_address')) {
                $table->json('kyc_residential_address')->nullable();
            }
            if (! Schema::hasColumn('whatsapp_wallets', 'kyc_expected_profile')) {
                $table->json('kyc_expected_profile')->nullable();
            }
            if (! Schema::hasColumn('whatsapp_wallets', 'kyc_id_document_type')) {
                $table->string('kyc_id_document_type', 32)->nullable();
            }
            if (! Schema::hasColumn('whatsapp_wallets', 'kyc_id_document_path')) {
                $table->string('kyc_id_document_path', 500)->nullable();
            }
            if (! Schema::hasColumn('whatsapp_wallets', 'kyc_id_document_status')) {
                $table->string('kyc_id_document_status', 24)->nullable();
            }
            if (! Schema::hasColumn('whatsapp_wallets', 'kyc_id_verified_at')) {
                $table->timestamp('kyc_id_verified_at')->nullable();
            }
            if (! Schema::hasColumn('whatsapp_wallets', 'kyc_address_evidence_path')) {
                $table->string('kyc_address_evidence_path', 500)->nullable();
            }
            if (! Schema::hasColumn('whatsapp_wallets', 'kyc_address_status')) {
                $table->string('kyc_address_status', 24)->nullable();
            }
            if (! Schema::hasColumn('whatsapp_wallets', 'kyc_address_verified_at')) {
                $table->timestamp('kyc_address_verified_at')->nullable();
            }
            if (! Schema::hasColumn('whatsapp_wallets', 'kyc_email_verified_at')) {
                $table->timestamp('kyc_email_verified_at')->nullable();
            }
            if (! Schema::hasColumn('whatsapp_wallets', 'kyc_bvn_verified_at')) {
                $table->timestamp('kyc_bvn_verified_at')->nullable();
            }
            if (! Schema::hasColumn('whatsapp_wallets', 'kyc_nin_verified_at')) {
                $table->timestamp('kyc_nin_verified_at')->nullable();
            }
            if (! Schema::hasColumn('whatsapp_wallets', 'kyc_mevon_full_name')) {
                $table->string('kyc_mevon_full_name', 255)->nullable();
            }
            if (! Schema::hasColumn('whatsapp_wallets', 'kyc_identity_reference')) {
                $table->string('kyc_identity_reference', 128)->nullable();
            }
            if (! Schema::hasColumn('whatsapp_wallets', 'kyc_identity_snapshot')) {
                $table->json('kyc_identity_snapshot')->nullable();
            }
            if (! Schema::hasColumn('whatsapp_wallets', 'daily_business_transfer_total')) {
                $table->decimal('daily_business_transfer_total', 14, 2)->default(0);
            }
            if (! Schema::hasColumn('whatsapp_wallets', 'daily_business_transfer_for_date')) {
                $table->date('daily_business_transfer_for_date')->nullable();
            }
        });

        if (! Schema::hasTable('kyc_audit_events')) {
            Schema::create('kyc_audit_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('whatsapp_wallet_id')->nullable()->constrained('whatsapp_wallets')->nullOnDelete();
                $table->unsignedBigInteger('business_account_application_id')->nullable();
                $table->string('actor_type', 32);
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('action', 64);
                $table->json('payload')->nullable();
                $table->string('ip', 45)->nullable();
                $table->timestamps();

                $table->index(['whatsapp_wallet_id', 'created_at'], 'kyc_audit_wallet_created_idx');
                $table->index('action', 'kyc_audit_action_idx');
            });
        }

        Schema::table('business_account_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('business_account_applications', 'cac_number')) {
                $table->string('cac_number', 64)->nullable();
            }
            if (! Schema::hasColumn('business_account_applications', 'tin')) {
                $table->string('tin', 64)->nullable();
            }
            if (! Schema::hasColumn('business_account_applications', 'registered_address')) {
                $table->text('registered_address')->nullable();
            }
            if (! Schema::hasColumn('business_account_applications', 'operating_address')) {
                $table->text('operating_address')->nullable();
            }
            if (! Schema::hasColumn('business_account_applications', 'actual_activity')) {
                $table->text('actual_activity')->nullable();
            }
            if (! Schema::hasColumn('business_account_applications', 'sector')) {
                $table->string('sector', 128)->nullable();
            }
            if (! Schema::hasColumn('business_account_applications', 'source_of_funds')) {
                $table->string('source_of_funds', 255)->nullable();
            }
            if (! Schema::hasColumn('business_account_applications', 'source_of_wealth')) {
                $table->string('source_of_wealth', 255)->nullable();
            }
            if (! Schema::hasColumn('business_account_applications', 'expected_profile')) {
                $table->json('expected_profile')->nullable();
            }
            if (! Schema::hasColumn('business_account_applications', 'kyb_status')) {
                $table->string('kyb_status', 32)->nullable();
            }
            if (! Schema::hasColumn('business_account_applications', 'memart_path')) {
                $table->string('memart_path', 500)->nullable();
            }
            if (! Schema::hasColumn('business_account_applications', 'cac_status_report_path')) {
                $table->string('cac_status_report_path', 500)->nullable();
            }
            if (! Schema::hasColumn('business_account_applications', 'licence_path')) {
                $table->string('licence_path', 500)->nullable();
            }
            if (! Schema::hasColumn('business_account_applications', 'address_evidence_path')) {
                $table->string('address_evidence_path', 500)->nullable();
            }
            if (! Schema::hasColumn('business_account_applications', 'cac_status_confirmed_at')) {
                $table->timestamp('cac_status_confirmed_at')->nullable();
            }
            if (! Schema::hasColumn('business_account_applications', 'edd_required')) {
                $table->boolean('edd_required')->default(false);
            }
            if (! Schema::hasColumn('business_account_applications', 'edd_approved_at')) {
                $table->timestamp('edd_approved_at')->nullable();
            }
            if (! Schema::hasColumn('business_account_applications', 'daily_limit_ngn')) {
                $table->decimal('daily_limit_ngn', 14, 2)->nullable();
            }
        });

        if (! Schema::hasTable('business_kyc_parties')) {
            Schema::create('business_kyc_parties', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_account_application_id')->constrained('business_account_applications')->cascadeOnDelete();
                $table->unsignedBigInteger('parent_party_id')->nullable();
                $table->string('role', 32);
                $table->string('person_type', 16);
                $table->string('legal_name', 255);
                $table->date('dob')->nullable();
                $table->string('nationality', 8)->nullable();
                $table->string('bvn', 11)->nullable();
                $table->string('nin', 11)->nullable();
                $table->string('email', 160)->nullable();
                $table->string('phone', 32)->nullable();
                $table->decimal('ownership_percent', 8, 4)->nullable();
                $table->boolean('is_nominee')->default(false);
                $table->timestamp('identity_verified_at')->nullable();
                $table->string('mevon_reference', 128)->nullable();
                $table->string('mevon_full_name', 255)->nullable();
                $table->string('id_document_path', 500)->nullable();
                $table->string('authority_document_path', 500)->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['business_account_application_id', 'role'], 'bkp_app_role_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('business_kyc_parties');
        Schema::dropIfExists('kyc_audit_events');

        Schema::table('whatsapp_wallets', function (Blueprint $table) {
            foreach ([
                'kyc_status', 'kyc_tier', 'kyc_nationality', 'kyc_occupation', 'kyc_employer',
                'kyc_purpose_of_account', 'kyc_source_of_funds', 'kyc_residential_address',
                'kyc_expected_profile', 'kyc_id_document_type', 'kyc_id_document_path',
                'kyc_id_document_status', 'kyc_id_verified_at', 'kyc_address_evidence_path',
                'kyc_address_status', 'kyc_address_verified_at', 'kyc_email_verified_at',
                'kyc_bvn_verified_at', 'kyc_nin_verified_at', 'kyc_mevon_full_name',
                'kyc_identity_reference', 'kyc_identity_snapshot',
                'daily_business_transfer_total', 'daily_business_transfer_for_date',
            ] as $col) {
                if (Schema::hasColumn('whatsapp_wallets', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('business_account_applications', function (Blueprint $table) {
            foreach ([
                'cac_number', 'tin', 'registered_address', 'operating_address', 'actual_activity',
                'sector', 'source_of_funds', 'source_of_wealth', 'expected_profile', 'kyb_status',
                'memart_path', 'cac_status_report_path', 'licence_path', 'address_evidence_path',
                'cac_status_confirmed_at', 'edd_required', 'edd_approved_at', 'daily_limit_ngn',
            ] as $col) {
                if (Schema::hasColumn('business_account_applications', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
