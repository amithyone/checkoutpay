<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->string('channel', 32)->default('business_dashboard')->after('id');
            $table->unsignedBigInteger('whatsapp_wallet_id')->nullable()->after('business_id');
            $table->string('visitor_name')->nullable()->after('message');
            $table->string('visitor_email')->nullable()->after('visitor_name');
            $table->string('visitor_phone', 20)->nullable()->after('visitor_email');
            $table->uuid('public_token')->nullable()->unique()->after('visitor_phone');
            $table->timestamp('wallet_onboarding_sent_at')->nullable()->after('public_token');
            $table->timestamp('last_message_at')->nullable()->after('wallet_onboarding_sent_at');
            $table->unsignedInteger('admin_unread_count')->default(0)->after('last_message_at');
            $table->unsignedInteger('visitor_unread_count')->default(0)->after('admin_unread_count');
            $table->string('last_visitor_ip', 45)->nullable()->after('visitor_unread_count');
            $table->text('user_agent')->nullable()->after('last_visitor_ip');

            $table->index('channel');
            $table->index('whatsapp_wallet_id');
            $table->index('last_message_at');
            $table->index(['channel', 'status']);
        });

        if (Schema::hasColumn('support_tickets', 'business_id')) {
            Schema::table('support_tickets', function (Blueprint $table) {
                $table->unsignedBigInteger('business_id')->nullable()->change();
            });
        }

        DB::table('support_tickets')->whereNull('channel')->update(['channel' => 'business_dashboard']);

        Schema::table('support_ticket_replies', function (Blueprint $table) {
            $table->timestamp('read_at')->nullable()->after('is_internal_note');
        });
    }

    public function down(): void
    {
        Schema::table('support_ticket_replies', function (Blueprint $table) {
            $table->dropColumn('read_at');
        });

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropIndex(['channel', 'status']);
            $table->dropIndex(['last_message_at']);
            $table->dropIndex(['whatsapp_wallet_id']);
            $table->dropIndex(['channel']);
            $table->dropColumn([
                'channel',
                'whatsapp_wallet_id',
                'visitor_name',
                'visitor_email',
                'visitor_phone',
                'public_token',
                'wallet_onboarding_sent_at',
                'last_message_at',
                'admin_unread_count',
                'visitor_unread_count',
                'last_visitor_ip',
                'user_agent',
            ]);
        });
    }
};
