<?php

namespace App\Services\Admin;

use App\Models\Admin;
use App\Models\Payment;
use App\Models\WhatsappWalletTransaction;
use App\Models\Renter;
use App\Models\SupportTicket;
use App\Models\VirtualCardRequest;
use Illuminate\Support\Facades\Route;

class AdminSidebarMenu
{
    /** @return list<array<string, mixed>> */
    public function itemsFor(Admin $admin): array
    {
        $registry = $this->registry($admin);
        $orderedKeys = $this->orderedKeys($admin);
        $items = [];

        foreach ($orderedKeys as $key) {
            if ($key === config('admin_sidebar.divider_key')) {
                $items[] = ['key' => $key, 'type' => 'divider'];

                continue;
            }

            $def = $registry[$key] ?? null;
            if ($def === null || ! ($def['visible'] ?? true)) {
                continue;
            }

            $items[] = array_merge($def, ['key' => $key, 'type' => 'link']);
        }

        return $items;
    }

    /** @return list<string> */
    public function orderedKeys(Admin $admin): array
    {
        $default = config('admin_sidebar.default_order', []);
        $saved = is_array($admin->sidebar_menu_order) ? $admin->sidebar_menu_order : [];

        if ($saved === []) {
            return $default;
        }

        $valid = array_flip($default);
        $merged = [];

        foreach ($saved as $key) {
            if (is_string($key) && isset($valid[$key])) {
                $merged[] = $key;
            }
        }

        foreach ($default as $key) {
            if (! in_array($key, $merged, true)) {
                $merged[] = $key;
            }
        }

        return $merged;
    }

    /** @param list<string> $order */
    public function saveOrder(Admin $admin, array $order): void
    {
        $valid = array_flip(config('admin_sidebar.default_order', []));
        $clean = [];

        foreach ($order as $key) {
            if (is_string($key) && isset($valid[$key])) {
                $clean[] = $key;
            }
        }

        foreach (config('admin_sidebar.default_order', []) as $key) {
            if (! in_array($key, $clean, true)) {
                $clean[] = $key;
            }
        }

        $admin->sidebar_menu_order = $clean;
        $admin->save();
    }

    public function resetOrder(Admin $admin): void
    {
        $admin->sidebar_menu_order = null;
        $admin->save();
    }

    /** @return array<string, array<string, mixed>> */
    private function registry(Admin $admin): array
    {
        $needsReviewCount = Payment::query()
            ->where('status', Payment::STATUS_PENDING)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->has('statusChecks', '>=', 3)
            ->count();

        $openTickets = SupportTicket::where('status', SupportTicket::STATUS_OPEN)->count();

        $pendingCardRequests = VirtualCardRequest::whereIn('status', [
            VirtualCardRequest::STATUS_PENDING,
            VirtualCardRequest::STATUS_SUBMITTED,
        ])->count();

        $pendingRenterKycCount = Renter::query()
            ->whereNotNull('kyc_id_card_path')
            ->where(function ($q) {
                $q->whereNull('kyc_id_status')->orWhere('kyc_id_status', 'pending');
            })
            ->count();

        return [
            'dashboard' => $this->link('Dashboard', 'admin.dashboard', 'fas fa-chart-line', ['admin.dashboard']),
            'payments' => $this->link('Payments', 'admin.payments.index', 'fas fa-money-bill-wave', ['admin.payments.*'], excludeRoutes: ['admin.payments.needs-review']),
            'payments_needs_review' => array_merge(
                $this->link('Needs Review', 'admin.payments.needs-review', 'fas fa-exclamation-triangle', ['admin.payments.needs-review']),
                [
                    'variant' => 'danger',
                    'badge_count' => $needsReviewCount,
                    'badge_color' => 'red',
                ]
            ),
            'businesses' => $this->link('Businesses', 'admin.businesses.index', 'fas fa-building', ['admin.businesses.*']),
            'support' => array_merge(
                $this->link('Support Tickets', 'admin.support.index', 'fas fa-comments', ['admin.support.*']),
                ['visible' => $admin->canManageSupportTickets(), 'badge_count' => $openTickets, 'badge_color' => 'red']
            ),
            'virtual_cards' => array_merge(
                $this->link('Card Management', 'admin.virtual-cards.index', 'fas fa-credit-card text-indigo-600', ['admin.virtual-cards.*']),
                ['visible' => $admin->canManageSettings(), 'badge_count' => $pendingCardRequests, 'badge_color' => 'indigo']
            ),
            'businesses_kyc' => $this->link('Business KYC', 'admin.businesses-kyc.index', 'fas fa-id-card', ['admin.businesses-kyc.*']),
            'renters_kyc' => array_merge(
                $this->link('Renters KYC', 'admin.renters-kyc.index', 'fas fa-id-badge', ['admin.renters-kyc.*']),
                ['badge_count' => $pendingRenterKycCount, 'badge_color' => 'yellow']
            ),
            'rentals' => $this->link('Rentals', 'admin.rentals.index', 'fas fa-camera', ['admin.rentals.*', 'admin.rental-categories.*', 'admin.rental-items.*']),
            'whatsapp_wallet' => array_merge(
                $this->link('WhatsApp wallet', 'admin.whatsapp-wallet.index', 'fab fa-whatsapp text-green-600', ['admin.whatsapp-wallet.index', 'admin.whatsapp-wallet.update', 'admin.whatsapp-wallet.fx-rates.update']),
                ['visible' => $admin->canManageSettings()]
            ),
            'whatsapp_wallet_transactions' => array_merge(
                $this->link('Wallet transactions', 'admin.whatsapp-wallet.transactions.index', 'fas fa-exchange-alt text-green-600', ['admin.whatsapp-wallet.transactions.*']),
                [
                    'visible' => $admin->canManageSettings(),
                    'badge_count' => WhatsappWalletTransaction::countFailedBankPayoutsRecent(),
                    'badge_color' => 'red',
                ]
            ),
            'withdrawals' => $this->link('Withdrawals', 'admin.withdrawals.index', 'fas fa-hand-holding-usd', ['admin.withdrawals.*']),
            'overdraft' => $this->link('Overdraft queue', 'admin.overdraft-applications.index', 'fas fa-file-invoice-dollar', ['admin.overdraft-applications.*']),
            'peer_lending_offers' => array_merge(
                $this->link('Peer lending offers', 'admin.peer-lending.offers.index', 'fas fa-hand-holding-usd', ['admin.peer-lending.offers.*']),
                ['visible' => $admin->isSuperAdmin()]
            ),
            'peer_lending_loans' => array_merge(
                $this->link('Peer loan queue', 'admin.peer-lending.loans.index', 'fas fa-money-check-alt', ['admin.peer-lending.loans.*']),
                ['visible' => $admin->isSuperAdmin()]
            ),
            'external_apis' => array_merge(
                $this->link('External APIs', 'admin.external-apis.index', 'fas fa-plug', ['admin.external-apis.*']),
                ['visible' => $admin->canManageAccountNumbers()]
            ),
            'processed_emails' => $this->link('Inbox', 'admin.processed-emails.index', 'fas fa-inbox', ['admin.processed-emails.*']),
            'transaction_logs' => $this->link('Transaction Logs', 'admin.transaction-logs.index', 'fas fa-history', ['admin.transaction-logs.*']),
            'audits' => array_merge(
                $this->link('Audits', 'admin.audits.index', 'fas fa-clipboard-check', ['admin.audits.*']),
                ['visible' => $admin->canManageSettings()]
            ),
            'match_attempts' => $this->link('Match Logs', 'admin.match-attempts.index', 'fas fa-search-dollar', ['admin.match-attempts.*']),
            'invoices' => $this->link('Invoices', 'admin.invoices.index', 'fas fa-file-invoice', ['admin.invoices.*']),
            'email_accounts' => array_merge(
                $this->link('Email Accounts', 'admin.email-accounts.index', 'fas fa-envelope', ['admin.email-accounts.*']),
                ['visible' => $admin->canManageEmailAccounts()]
            ),
            'account_numbers' => array_merge(
                $this->link('Account Numbers', 'admin.account-numbers.index', 'fas fa-hashtag', ['admin.account-numbers.*']),
                ['visible' => $admin->canManageAccountNumbers()]
            ),
            'bank_email_templates' => $this->link('Bank Templates', 'admin.bank-email-templates.index', 'fas fa-university', ['admin.bank-email-templates.*']),
            'test_transaction' => $this->link('Test Transaction', 'admin.test-transaction.index', 'fas fa-flask', ['admin.test-transaction.*']),
            'renters' => $this->link('Rental users', 'admin.renters.index', 'fas fa-users', ['admin.renters.index']),
            'tickets' => $this->link('Tickets', 'admin.tickets.events.index', 'fas fa-ticket-alt', ['admin.tickets.*'], excludeRoutes: ['admin.tickets.scanner', 'admin.tickets.scanner*']),
            'tickets_scanner' => $this->link('QR Scanner', 'admin.tickets.scanner', 'fas fa-qrcode', ['admin.tickets.scanner', 'admin.tickets.scanner*']),
            'charity' => $this->link('Go Fund', 'admin.charity.index', 'fas fa-hand-holding-heart', ['admin.charity.*']),
            'memberships' => $this->link('Memberships', 'admin.memberships.index', 'fas fa-address-card', ['admin.memberships.*', 'admin.membership-categories.*']),
            'developer_program' => $this->link('Developer program', 'admin.developer-program.index', 'fas fa-handshake', ['admin.developer-program.*']),
            'desktop_telemetry' => array_merge(
                $this->link('Desktop DRM', 'admin.desktop-telemetry.events.index', 'fas fa-laptop-code', ['admin.desktop-telemetry.*']),
                ['visible' => $admin->isSuperAdmin()]
            ),
            'whitelisted_emails' => array_merge(
                $this->link('Whitelisted Emails', 'admin.whitelisted-emails.index', 'fas fa-shield-alt', ['admin.whitelisted-emails.*']),
                ['visible' => $admin->canManageSettings()]
            ),
            'pages' => array_merge(
                $this->link('Pages', 'admin.pages.index', 'fas fa-file-alt', ['admin.pages.*']),
                ['visible' => $admin->canManageSettings()]
            ),
            'settings' => array_merge(
                $this->link('Settings', 'admin.settings.index', 'fas fa-cog', ['admin.settings.*']),
                ['visible' => $admin->canManageSettings()]
            ),
            'email_templates' => array_merge(
                $this->link('Email Templates', 'admin.email-templates.index', 'fas fa-envelope-open-text', ['admin.email-templates.*']),
                ['visible' => $admin->canManageSettings()]
            ),
            'staff' => array_merge(
                $this->link('Staff Management', 'admin.staff.index', 'fas fa-users-cog', ['admin.staff.*']),
                ['visible' => $admin->canManageAdmins()]
            ),
        ];
    }

    /**
     * @param  list<string>  $activeRoutes
     * @param  list<string>  $excludeRoutes
     * @return array<string, mixed>
     */
    private function link(string $label, string $routeName, string $icon, array $activeRoutes, array $excludeRoutes = []): array
    {
        $isActive = false;
        foreach ($activeRoutes as $pattern) {
            if (request()->routeIs($pattern)) {
                $isActive = true;
                break;
            }
        }
        foreach ($excludeRoutes as $pattern) {
            if (request()->routeIs($pattern)) {
                $isActive = false;
                break;
            }
        }

        return [
            'label' => $label,
            'url' => Route::has($routeName) ? route($routeName) : '#',
            'icon' => $icon,
            'is_active' => $isActive,
            'visible' => true,
            'variant' => 'default',
            'badge_count' => 0,
            'badge_color' => 'red',
        ];
    }
}
