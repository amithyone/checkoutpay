<?php

namespace App\Services\Support;

use App\Models\Admin;
use App\Models\ConsumerWalletApiAccount;
use App\Models\SupportTicket;
use App\Services\Whatsapp\PhoneNormalizer;

final class WalletSupportStaffResolver
{
    public function __construct(
        private SupportIssueOptionsService $issues,
    ) {}

    public function resolveForAccount(?ConsumerWalletApiAccount $account): ?Admin
    {
        $phone = $this->accountPhoneDigits($account);
        if ($phone === null) {
            return null;
        }

        return Admin::query()
            ->where('role', Admin::ROLE_WALLET_SUPPORT)
            ->where('is_active', true)
            ->where('handles_wallet_support_in_app', true)
            ->whereNotNull('whatsapp_e164')
            ->where('whatsapp_e164', '!=', '')
            ->get()
            ->first(fn (Admin $admin) => $this->normalizePhone((string) $admin->whatsapp_e164) === $phone);
    }

    public function isStaffAccount(?ConsumerWalletApiAccount $account): bool
    {
        return $this->resolveForAccount($account) !== null;
    }

    /**
     * @return list<string> normalized E.164 digit strings for active in-app wallet support staff
     */
    public function staffPhoneDigits(): array
    {
        $phones = [];
        Admin::query()
            ->where('role', Admin::ROLE_WALLET_SUPPORT)
            ->where('is_active', true)
            ->where('handles_wallet_support_in_app', true)
            ->whereNotNull('whatsapp_e164')
            ->where('whatsapp_e164', '!=', '')
            ->pluck('whatsapp_e164')
            ->each(function (string $raw) use (&$phones): void {
                $normalized = $this->normalizePhone($raw);
                if ($normalized !== null) {
                    $phones[$normalized] = $normalized;
                }
            });

        return array_values($phones);
    }

    public function unreadWalletQueueTotal(): int
    {
        return (int) $this->walletQueueTicketQuery()
            ->where('admin_unread_count', '>', 0)
            ->sum('admin_unread_count');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<SupportTicket>
     */
    public function walletQueueTicketQuery()
    {
        $walletKeys = $this->issues->walletIssueTypeKeys();
        $staffPhones = $this->staffPhoneDigits();

        return SupportTicket::query()
            ->where('channel', SupportTicket::CHANNEL_CHECKOUTNOW_APP)
            ->whereIn('status', [SupportTicket::STATUS_OPEN, SupportTicket::STATUS_IN_PROGRESS])
            ->where(function ($query) use ($walletKeys): void {
                $query->where('support_queue', SupportIssueOptionsService::QUEUE_WALLET);
                if ($walletKeys !== []) {
                    $query->orWhereIn('issue_type', $walletKeys);
                }
            })
            ->when($staffPhones !== [], function ($query) use ($staffPhones): void {
                $query->where(function ($inner) use ($staffPhones): void {
                    $inner->whereNull('visitor_phone')
                        ->orWhereNotIn('visitor_phone', $staffPhones);
                });
            });
    }

    private function accountPhoneDigits(?ConsumerWalletApiAccount $account): ?string
    {
        if (! $account) {
            return null;
        }

        $raw = trim((string) ($account->phone_e164 ?? ''));
        if ($raw === '' && $account->whatsapp_wallet_id) {
            $account->loadMissing('wallet');
            $raw = trim((string) ($account->wallet?->phone_e164 ?? ''));
        }

        return $this->normalizePhone($raw);
    }

    private function normalizePhone(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        return PhoneNormalizer::canonicalAuthE164Digits($raw);
    }
}
