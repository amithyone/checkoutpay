<?php

namespace App\Services;

use App\Models\AccountNumber;
use App\Models\Business;
use App\Models\BusinessActivityLog;
use App\Models\BusinessNotification;
use App\Models\BusinessTransaction;
use App\Models\BusinessVerification;
use App\Models\BusinessWebsite;
use App\Models\BusinessWithdrawalAccount;
use App\Models\CharityCampaign;
use App\Models\EmailAccount;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\MatchAttempt;
use App\Models\Membership;
use App\Models\MembershipSubscription;
use App\Models\Payment;
use App\Models\PaymentStatusCheck;
use App\Models\Rental;
use App\Models\RentalDeviceToken;
use App\Models\RentalItem;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketType;
use App\Models\TransactionLog;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BusinessDataExportService
{
    /**
     * Build a portable JSON payload for one business (and linked user rows).
     *
     * @return array{meta: array, tables: array<string, array<int, array<string, mixed>>>}
     */
    public function buildPayload(int $businessId, ?int $sourceUserId = null): array
    {
        $business = Business::withTrashed()->find($businessId);
        if (! $business) {
            throw new \InvalidArgumentException("Business id {$businessId} not found.");
        }

        $paymentIds = Payment::withTrashed()->where('business_id', $businessId)->pluck('id')->all();
        $invoiceIds = Schema::hasTable('invoices')
            ? DB::table('invoices')->where('business_id', $businessId)->pluck('id')->all()
            : [];
        $supportTicketIds = Schema::hasTable('support_tickets')
            ? DB::table('support_tickets')->where('business_id', $businessId)->pluck('id')->all()
            : [];
        $eventIds = Schema::hasTable('events')
            ? DB::table('events')->where('business_id', $businessId)->pluck('id')->all()
            : [];
        $membershipIds = Schema::hasTable('memberships')
            ? DB::table('memberships')->where('business_id', $businessId)->pluck('id')->all()
            : [];
        $ticketOrderIds = Schema::hasTable('ticket_orders')
            ? DB::table('ticket_orders')->where('business_id', $businessId)->pluck('id')->all()
            : [];

        $tables = [];

        $tables['businesses'] = [Business::withTrashed()->find($businessId)?->toArray() ?? []];
        $tables['businesses'] = array_values(array_filter($tables['businesses']));

        $tables['users'] = Schema::hasTable('users')
            ? User::query()->where('business_id', $businessId)->get()->map->toArray()->values()->all()
            : [];

        if ($business->email_account_id && Schema::hasTable('email_accounts')) {
            $ea = EmailAccount::withTrashed()->find($business->email_account_id);
            $tables['email_accounts'] = $ea ? [$ea->toArray()] : [];
        } else {
            $tables['email_accounts'] = [];
        }

        $tables['business_websites'] = BusinessWebsite::withTrashed()->where('business_id', $businessId)->get()->map->toArray()->values()->all();

        if (Schema::hasTable('business_external_api')) {
            $tables['business_external_api'] = DB::table('business_external_api')
                ->where('business_id', $businessId)
                ->get()
                ->map(fn ($r) => (array) $r)
                ->values()
                ->all();
        }

        $tables['account_numbers'] = AccountNumber::withTrashed()->where('business_id', $businessId)->get()->map->toArray()->values()->all();
        $tables['payments'] = Payment::withTrashed()->where('business_id', $businessId)->get()->map->toArray()->values()->all();

        if (! empty($paymentIds) && Schema::hasTable('match_attempts')) {
            $tables['match_attempts'] = MatchAttempt::query()
                ->whereIn('payment_id', $paymentIds)
                ->get()
                ->map->toArray()
                ->values()
                ->all();
        } else {
            $tables['match_attempts'] = [];
        }

        if (Schema::hasTable('payment_status_checks')) {
            $tables['payment_status_checks'] = PaymentStatusCheck::query()
                ->where('business_id', $businessId)
                ->get()
                ->map->toArray()
                ->values()
                ->all();
        }

        if (Schema::hasTable('invoices')) {
            $tables['invoices'] = Invoice::withTrashed()->where('business_id', $businessId)->get()->map->toArray()->values()->all();
        }
        if (! empty($invoiceIds) && Schema::hasTable('invoice_items')) {
            $tables['invoice_items'] = DB::table('invoice_items')->whereIn('invoice_id', $invoiceIds)->get()->map(fn ($r) => (array) $r)->values()->all();
        } else {
            $tables['invoice_items'] = [];
        }
        if (! empty($invoiceIds) && Schema::hasTable('invoice_payments')) {
            $tables['invoice_payments'] = DB::table('invoice_payments')->whereIn('invoice_id', $invoiceIds)->get()->map(fn ($r) => (array) $r)->values()->all();
        } else {
            $tables['invoice_payments'] = [];
        }

        $tables['withdrawal_requests'] = WithdrawalRequest::withTrashed()->where('business_id', $businessId)->get()->map->toArray()->values()->all();
        $tables['business_withdrawal_accounts'] = BusinessWithdrawalAccount::withTrashed()->where('business_id', $businessId)->get()->map->toArray()->values()->all();
        $tables['business_verifications'] = BusinessVerification::withTrashed()->where('business_id', $businessId)->get()->map->toArray()->values()->all();
        $tables['business_activity_logs'] = BusinessActivityLog::query()->where('business_id', $businessId)->get()->map->toArray()->values()->all();
        $tables['business_notifications'] = BusinessNotification::query()->where('business_id', $businessId)->get()->map->toArray()->values()->all();

        if (Schema::hasTable('support_tickets')) {
            $tables['support_tickets'] = SupportTicket::withTrashed()->where('business_id', $businessId)->get()->map->toArray()->values()->all();
        }
        if (! empty($supportTicketIds) && Schema::hasTable('support_ticket_replies')) {
            $tables['support_ticket_replies'] = SupportTicketReply::query()
                ->whereIn('ticket_id', $supportTicketIds)
                ->get()
                ->map->toArray()
                ->values()
                ->all();
        } else {
            $tables['support_ticket_replies'] = [];
        }

        if (Schema::hasTable('rentals')) {
            $tables['rentals'] = Rental::withTrashed()->where('business_id', $businessId)->get()->map->toArray()->values()->all();
        }
        if (Schema::hasTable('rental_items')) {
            $tables['rental_items'] = RentalItem::withTrashed()->where('business_id', $businessId)->get()->map->toArray()->values()->all();
        }
        if (Schema::hasTable('rental_device_tokens')) {
            $tables['rental_device_tokens'] = RentalDeviceToken::query()->where('business_id', $businessId)->get()->map->toArray()->values()->all();
        }

        if (Schema::hasTable('memberships')) {
            $tables['memberships'] = Membership::withTrashed()->where('business_id', $businessId)->get()->map->toArray()->values()->all();
        }
        if (! empty($membershipIds) && Schema::hasTable('membership_subscriptions')) {
            $tables['membership_subscriptions'] = MembershipSubscription::withTrashed()
                ->whereIn('membership_id', $membershipIds)
                ->get()
                ->map->toArray()
                ->values()
                ->all();
        } else {
            $tables['membership_subscriptions'] = [];
        }

        if (Schema::hasTable('charity_campaigns')) {
            $tables['charity_campaigns'] = CharityCampaign::query()->where('business_id', $businessId)->get()->map->toArray()->values()->all();
        }

        if (Schema::hasTable('events')) {
            $tables['events'] = Event::query()->where('business_id', $businessId)->get()->map->toArray()->values()->all();
        }
        if (! empty($eventIds) && Schema::hasTable('ticket_types')) {
            $tables['ticket_types'] = TicketType::withTrashed()->whereIn('event_id', $eventIds)->get()->map->toArray()->values()->all();
        } else {
            $tables['ticket_types'] = [];
        }

        if (Schema::hasTable('ticket_orders')) {
            $tables['ticket_orders'] = TicketOrder::withTrashed()->where('business_id', $businessId)->get()->map->toArray()->values()->all();
        }
        if (! empty($ticketOrderIds) && Schema::hasTable('tickets')) {
            $tables['tickets'] = Ticket::withTrashed()->whereIn('ticket_order_id', $ticketOrderIds)->get()->map->toArray()->values()->all();
        } else {
            $tables['tickets'] = [];
        }

        if (Schema::hasTable('business_transactions')) {
            $tables['business_transactions'] = BusinessTransaction::query()->where('business_id', $businessId)->get()->map->toArray()->values()->all();
        }
        if (Schema::hasTable('transaction_logs')) {
            $tables['transaction_logs'] = TransactionLog::query()->where('business_id', $businessId)->get()->map->toArray()->values()->all();
        }

        $meta = [
            'export_version' => 1,
            'exported_at' => now()->toIso8601String(),
            'business_id' => $businessId,
            'source_user_id' => $sourceUserId,
            'business_public_code' => $business->business_id,
            'import_order' => [
                'email_accounts',
                'businesses',
                'users',
                'business_websites',
                'business_external_api',
                'account_numbers',
                'payments',
                'match_attempts',
                'payment_status_checks',
                'invoices',
                'invoice_items',
                'invoice_payments',
                'withdrawal_requests',
                'business_withdrawal_accounts',
                'business_verifications',
                'business_activity_logs',
                'business_notifications',
                'support_tickets',
                'support_ticket_replies',
                'rentals',
                'rental_items',
                'rental_device_tokens',
                'memberships',
                'membership_subscriptions',
                'charity_campaigns',
                'events',
                'ticket_types',
                'ticket_orders',
                'tickets',
                'business_transactions',
                'transaction_logs',
            ],
            'warnings' => [
                'Contains passwords (hashed), API keys, KYC paths, and PII. Store encrypted; do not commit to git.',
                'Live import: IDs may conflict. Prefer a staging DB or remap FKs. Set FOREIGN_KEY_CHECKS=0 only if you understand the risks.',
                'business_external_api references external_apis.id — ensure the same provider rows exist on live (or remap external_api_id).',
                'Payments reference account_numbers, business_websites, renters, etc. Import in dependency order or keep same IDs.',
            ],
        ];

        return [
            'meta' => $meta,
            'tables' => $tables,
        ];
    }

    public function writeJsonFile(int $businessId, ?int $sourceUserId, ?string $path = null): string
    {
        $payload = $this->buildPayload($businessId, $sourceUserId);
        $dir = storage_path('app/business-exports');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $filename = $path ?? ($dir.'/export-business-'.$businessId.'-'.now()->format('Y-m-d-His').'.json');
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            throw new \RuntimeException('JSON encode failed: '.json_last_error_msg());
        }
        file_put_contents($filename, $json);

        return $filename;
    }
}
