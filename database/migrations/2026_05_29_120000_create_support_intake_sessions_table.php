<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_intake_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('intake_token')->unique();
            $table->string('channel', 32)->default('checkout_web');
            $table->string('intake_status', 32)->default('in_progress');
            $table->string('current_step', 64)->default('disclaimer');
            $table->string('issue_type', 64)->nullable();
            $table->boolean('is_payment_issue')->nullable();
            $table->string('reported_destination_account', 32)->nullable();
            $table->string('reported_destination_bank', 120)->nullable();
            $table->string('reported_payee_name', 120)->nullable();
            $table->string('payment_session_id', 64)->nullable();
            $table->decimal('payment_amount_reported', 14, 2)->nullable();
            $table->string('visitor_name', 120)->nullable();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->boolean('account_on_session')->default(false);
            $table->boolean('account_in_platform')->default(false);
            $table->timestamp('whatsapp_eligible_at')->nullable();
            $table->string('payment_receipt_path')->nullable();
            $table->boolean('link_whatsapp_wallet')->default(false);
            $table->string('visitor_phone', 20)->nullable();
            $table->char('visitor_country', 2)->nullable();
            $table->unsignedBigInteger('whatsapp_wallet_id')->nullable();
            $table->unsignedBigInteger('consumer_wallet_api_account_id')->nullable();
            $table->unsignedBigInteger('support_ticket_id')->nullable();
            $table->uuid('public_token')->nullable();
            $table->json('bot_messages')->nullable();
            $table->timestamps();

            $table->index('intake_status');
            $table->index('support_ticket_id');
        });

        Schema::table('support_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('support_tickets', 'intake_status')) {
                $table->string('intake_status', 32)->nullable()->after('issue_type');
            }
            if (! Schema::hasColumn('support_tickets', 'reported_destination_account')) {
                $table->string('reported_destination_account', 32)->nullable()->after('payment_amount_reported');
            }
            if (! Schema::hasColumn('support_tickets', 'reported_destination_bank')) {
                $table->string('reported_destination_bank', 120)->nullable()->after('reported_destination_account');
            }
            if (! Schema::hasColumn('support_tickets', 'reported_payee_name')) {
                $table->string('reported_payee_name', 120)->nullable()->after('reported_destination_bank');
            }
            if (! Schema::hasColumn('support_tickets', 'whatsapp_eligible_at')) {
                $table->timestamp('whatsapp_eligible_at')->nullable()->after('wallet_onboarding_sent_at');
            }
            if (! Schema::hasColumn('support_tickets', 'payment_receipt_path')) {
                $table->string('payment_receipt_path')->nullable()->after('whatsapp_eligible_at');
            }
            if (! Schema::hasColumn('support_tickets', 'account_on_session')) {
                $table->boolean('account_on_session')->default(false)->after('payment_receipt_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $cols = [
                'intake_status',
                'reported_destination_account',
                'reported_destination_bank',
                'reported_payee_name',
                'whatsapp_eligible_at',
                'payment_receipt_path',
                'account_on_session',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('support_tickets', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('support_intake_sessions');
    }
};
