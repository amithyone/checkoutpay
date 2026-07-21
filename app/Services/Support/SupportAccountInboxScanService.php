<?php

namespace App\Services\Support;

use App\Models\ProcessedEmail;
use Illuminate\Support\Collection;

final class SupportAccountInboxScanService
{
    /**
     * @return array{count: int, emails: Collection<int, ProcessedEmail>}
     */
    public function scanUnmatchedForAccount(string $accountNumber, string $bankKey, int $limit = 20): array
    {
        $account = SupportPayeeAccountService::normalizeAccountNumber($accountNumber);
        if ($account === '') {
            return ['count' => 0, 'emails' => collect()];
        }

        try {
            $query = ProcessedEmail::query()
                ->unmatched()
                ->fromWhitelisted()
                ->where('account_number', $account)
                ->orderByDesc('email_date')
                ->limit($limit);

            $this->applyBankFilter($query, $bankKey);

            $emails = $query->get();
        } catch (\Throwable) {
            return ['count' => 0, 'emails' => collect()];
        }

        return [
            'count' => $emails->count(),
            'emails' => $emails,
        ];
    }

    /**
     * @return Collection<int, ProcessedEmail>
     */
    public function scanMatchedForIntake(string $accountNumber, string $bankKey, \Illuminate\Support\Carbon $since, int $limit = 100): Collection
    {
        $account = SupportPayeeAccountService::normalizeAccountNumber($accountNumber);
        if ($account === '') {
            return collect();
        }

        try {
            $query = ProcessedEmail::query()
                ->with('matchedPayment')
                ->where('is_matched', true)
                ->whereNotNull('matched_payment_id')
                ->where('email_date', '>=', $since)
                ->orderByDesc('email_date')
                ->limit($limit);

            $this->applyBankFilter($query, $bankKey);

            if ($bankKey === 'moniepoint_mfb') {
                $query->where('account_number', $account);
            }

            return $query->get();
        } catch (\Throwable) {
            return collect();
        }
    }

    private function applyBankFilter($query, string $bankKey): void
    {
        if ($bankKey === 'moniepoint_mfb') {
            $query->whereRaw('LOWER(COALESCE(from_email, "")) LIKE ?', ['%moniepoint.com%']);

            return;
        }

        if ($bankKey === 'kuda') {
            $query->where(function ($inner) {
                $inner->whereRaw('LOWER(COALESCE(from_email, "")) LIKE ?', ['%kuda%'])
                    ->orWhereRaw('LOWER(COALESCE(from_email, "")) LIKE ?', ['%kudabank%']);
            });
        }
    }
}
