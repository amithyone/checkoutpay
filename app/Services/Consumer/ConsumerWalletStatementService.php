<?php

namespace App\Services\Consumer;

use App\Mail\WalletStatementMail;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\Whatsapp\WhatsappWalletCountryResolver;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

final class ConsumerWalletStatementService
{
    public function __construct(
        private ConsumerBusinessActivityService $businessActivity,
        private ConsumerBusinessWalletLedgerService $businessLedger,
        private WhatsappWalletCountryResolver $walletCountry,
    ) {}

    /**
     * @param  array{format: string, ledger_scope: string, period?: string|null, from: string, to: string}  $input
     * @return array{email_masked: string, format: string, period_label: string}
     */
    public function sendEmail(WhatsappWallet $wallet, array $input): array
    {
        $wallet = $wallet->fresh(['linkedBusiness']);
        $email = trim((string) ($wallet->kyc_email ?? ''));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'email' => 'Add a valid email on your profile before requesting a statement.',
            ]);
        }

        $format = strtolower(trim((string) $input['format']));
        $scope = ConsumerWalletTransactionScope::normalize((string) $input['ledger_scope']);
        $from = trim((string) $input['from']);
        $to = trim((string) $input['to']);
        $period = trim((string) ($input['period'] ?? ''));
        $tz = (string) config('app.timezone', 'Africa/Lagos');

        try {
            Carbon::parse($from, $tz);
            Carbon::parse($to, $tz);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'from' => 'Invalid from or to date. Use YYYY-MM-DD.',
            ]);
        }

        if ($from > $to) {
            throw ValidationException::withMessages([
                'from' => 'Start date must be on or before end date.',
            ]);
        }

        if ($scope === ConsumerWalletTransactionScope::SCOPE_BUSINESS) {
            $this->businessLedger->refreshLinkedBalanceCache($wallet);
        }

        $rows = $this->loadTransactions($wallet, $scope, $from, $to);
        $rows = ConsumerAccountStatementBuilder::filterTransactionsInRange($rows, $from, $to);

        $currency = $this->walletCountry->currencyForPhoneE164((string) $wallet->phone_e164);
        $ledgerLabel = $scope === ConsumerWalletTransactionScope::SCOPE_BUSINESS
            ? 'Business wallet'
            : 'Personal wallet';
        $periodLabel = $period !== ''
            ? ConsumerAccountStatementBuilder::statementPeriodLabel($period)
            : sprintf('%s to %s', $from, $to);
        $accountName = trim((string) ($wallet->displayName() ?? $wallet->sender_name ?? 'Wallet'));

        $statementInput = [
            'ledger_label' => $ledgerLabel,
            'period_label' => $periodLabel,
            'from' => $from,
            'to' => $to,
            'phone' => (string) $wallet->phone_e164,
            'account_name' => $accountName !== '' ? $accountName : 'Wallet',
            'currency' => $currency,
        ];

        $slug = $scope === ConsumerWalletTransactionScope::SCOPE_BUSINESS ? 'business' : 'personal';
        $stamp = str_replace('-', '', $to);

        if ($format === 'csv') {
            $content = ConsumerAccountStatementBuilder::statementCsvContent($statementInput, $rows);
            $fileName = "checkoutnow-statement-{$slug}-{$stamp}.csv";
            $mime = 'text/csv';
        } elseif ($format === 'pdf') {
            $html = ConsumerAccountStatementBuilder::statementHtmlContent($statementInput, $rows);
            $content = Pdf::loadHTML($html)->output();
            $fileName = "checkoutnow-statement-{$slug}-{$stamp}.pdf";
            $mime = 'application/pdf';
        } else {
            throw ValidationException::withMessages([
                'format' => 'Format must be csv or pdf.',
            ]);
        }

        Mail::to($email)->send(new WalletStatementMail(
            recipientName: $accountName !== '' ? $accountName : 'there',
            ledgerLabel: $ledgerLabel,
            periodLabel: $periodLabel,
            from: $from,
            to: $to,
            format: $format,
            fileName: $fileName,
            fileContent: $content,
            mimeType: $mime,
        ));

        return [
            'email_masked' => ConsumerAccountStatementBuilder::maskEmail($email),
            'format' => $format,
            'period_label' => $periodLabel,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadTransactions(WhatsappWallet $wallet, string $scope, string $from, string $to): array
    {
        $tz = (string) config('app.timezone', 'Africa/Lagos');
        $fromAt = Carbon::parse($from, $tz)->startOfDay();
        $toAt = Carbon::parse($to, $tz)->endOfDay();

        if ($scope === ConsumerWalletTransactionScope::SCOPE_BUSINESS) {
            $business = $this->businessLedger->resolveLinkedOrMatchedBusiness($wallet);
            if ($business !== null) {
                return $this->fetchAllBusinessActivityRows($wallet, $business, $from, $to);
            }

            return WhatsappWalletTransaction::query()
                ->where('whatsapp_wallet_id', $wallet->id)
                ->where('ledger_scope', ConsumerWalletTransactionScope::SCOPE_BUSINESS)
                ->where('created_at', '>=', $fromAt)
                ->where('created_at', '<=', $toAt)
                ->orderByDesc('id')
                ->get()
                ->map(static fn (WhatsappWalletTransaction $tx): array => $tx->toArray())
                ->all();
        }

        $query = WhatsappWalletTransaction::query()->where('whatsapp_wallet_id', $wallet->id);
        ConsumerWalletTransactionScope::apply($query, ConsumerWalletTransactionScope::SCOPE_PERSONAL);

        return $query
            ->where('created_at', '>=', $fromAt)
            ->where('created_at', '<=', $toAt)
            ->orderByDesc('id')
            ->get()
            ->map(static fn (WhatsappWalletTransaction $tx): array => $tx->toArray())
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAllBusinessActivityRows(
        WhatsappWallet $wallet,
        \App\Models\Business $business,
        string $from,
        string $to,
    ): array {
        $perPage = 50;
        $page = 1;
        $rows = [];
        do {
            $result = $this->businessActivity->paginate(
                $wallet,
                $business,
                $from,
                $to,
                $page,
                $perPage,
                ConsumerBusinessActivityService::VIEW_FULL,
                false,
            );
            foreach ($result['items'] as $item) {
                $rows[] = $item['row'];
            }
            $total = (int) $result['total'];
            $page++;
        } while (($page - 1) * $perPage < $total);

        return $rows;
    }
}
