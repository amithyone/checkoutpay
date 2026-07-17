<?php

namespace App\Services\Cashwyre;

/**
 * Read-only Cashwyre Kenya discovery: banks/wallets for payout & collection.
 */
final class CashwyreKenyaDiscoveryService
{
    public function __construct(
        protected CashwyreHttpClient $http
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function discover(): array
    {
        $snapshot = [
            'discovered_at' => now()->toIso8601String(),
            'configured' => $this->http->isConfigured(),
            'currency' => 'KES',
            'country' => 'KE',
            'bank_payout' => false,
            'mpesa_payout' => false,
            'mpesa_collection' => false,
            'bank_collection' => false,
            'bills' => false,
            'airtime' => false,
            'banks_payout_count' => 0,
            'wallets_payout_count' => 0,
            'banks_collection_count' => 0,
            'wallets_collection_count' => 0,
            'sample_bank_codes' => [],
            'sample_wallet_codes' => [],
            'last_error' => null,
            'notes' => '',
            'raw' => [],
        ];

        if (! $this->http->isConfigured()) {
            $snapshot['last_error'] = 'Cashwyre is not configured.';
            $snapshot['notes'] = 'Set CASHWYRE_* env vars, enable KES on the business wallet, then re-run discovery.';

            return $snapshot;
        }

        $banksPayout = $this->fetchBanks('bank', 'payout');
        $walletsPayout = $this->fetchBanks('wallet', 'payout');
        $banksCollection = $this->fetchBanks('bank', 'collection');
        $walletsCollection = $this->fetchBanks('wallet', 'collection');

        $errors = array_filter([
            $banksPayout['error'] ?? null,
            $walletsPayout['error'] ?? null,
            $banksCollection['error'] ?? null,
            $walletsCollection['error'] ?? null,
        ]);

        $snapshot['raw'] = [
            'banks_payout' => $banksPayout['items'],
            'wallets_payout' => $walletsPayout['items'],
            'banks_collection' => $banksCollection['items'],
            'wallets_collection' => $walletsCollection['items'],
        ];

        $traditionalBanksPayout = array_values(array_filter(
            $banksPayout['items'],
            fn (array $row) => ! $this->looksLikeMobileMoney($row)
        ));
        $mobileMoneyPayout = array_values(array_filter(
            array_merge($banksPayout['items'], $walletsPayout['items']),
            fn (array $row) => $this->looksLikeMobileMoney($row)
        ));
        $traditionalBanksCollection = array_values(array_filter(
            $banksCollection['items'],
            fn (array $row) => ! $this->looksLikeMobileMoney($row)
        ));
        $mobileMoneyCollection = array_values(array_filter(
            array_merge($banksCollection['items'], $walletsCollection['items']),
            fn (array $row) => $this->looksLikeMobileMoney($row)
        ));

        $snapshot['banks_payout_count'] = count($traditionalBanksPayout);
        $snapshot['wallets_payout_count'] = count($mobileMoneyPayout);
        $snapshot['banks_collection_count'] = count($traditionalBanksCollection);
        $snapshot['wallets_collection_count'] = count($mobileMoneyCollection);

        $snapshot['bank_payout'] = $snapshot['banks_payout_count'] > 0;
        $snapshot['bank_collection'] = $snapshot['banks_collection_count'] > 0;

        $hasMpesa = false;
        foreach ($mobileMoneyPayout as $row) {
            if ($this->looksLikeMpesa($row)) {
                $hasMpesa = true;
                break;
            }
        }
        if (! $hasMpesa) {
            foreach ($mobileMoneyCollection as $row) {
                if ($this->looksLikeMpesa($row)) {
                    $hasMpesa = true;
                    break;
                }
            }
        }

        $snapshot['mpesa_payout'] = $hasMpesa && count($mobileMoneyPayout) > 0;
        $snapshot['mpesa_collection'] = $hasMpesa && count($mobileMoneyCollection) > 0;

        $snapshot['sample_bank_codes'] = array_slice(array_values(array_filter(array_map(
            static fn ($row) => (string) ($row['code'] ?? $row['bankCode'] ?? ''),
            $traditionalBanksPayout
        ))), 0, 10);

        $snapshot['sample_wallet_codes'] = array_slice(array_values(array_unique(array_filter(array_map(
            static fn ($row) => strtoupper((string) ($row['code'] ?? $row['bankCode'] ?? '')),
            $mobileMoneyPayout
        )))), 0, 10);

        // Bills/airtime: Cashwyre docs list them; we probe airtime buy info lightly if path exists.
        $airtimeProbe = $this->probeAirtimeCatalog();
        $snapshot['airtime'] = (bool) ($airtimeProbe['ok'] ?? false);
        $snapshot['bills'] = (bool) ($airtimeProbe['bills_ok'] ?? false);
        $snapshot['raw']['airtime_probe'] = $airtimeProbe;

        if ($errors !== []) {
            $snapshot['last_error'] = implode('; ', $errors);
        }

        $notes = [];
        if (! $snapshot['bank_payout'] && ! $snapshot['mpesa_payout']) {
            $notes[] = 'No KE payout banks/wallets returned — enable KES payouts on Cashwyre or check credentials.';
        }
        if (! $snapshot['bank_collection'] && ! $snapshot['mpesa_collection']) {
            $notes[] = 'No KE collection rails — Kenyan pay-in VA remains unavailable.';
        }
        if (! $snapshot['bills'] && ! $snapshot['airtime']) {
            $notes[] = 'Bills/airtime catalog not confirmed for KE on this account.';
        }
        $snapshot['notes'] = implode(' ', $notes);

        // Do not persist huge raw lists into forever cache consumers need — trim for storage.
        unset($snapshot['raw']);

        return $snapshot;
    }

    /**
     * @return array{items: list<array<string, mixed>>, error: ?string}
     */
    protected function fetchBanks(string $accountType, string $transactionType): array
    {
        $path = (string) config('cashwyre.paths.get_country_banks', '/CountryBank/getCountryBanks');
        $result = $this->http->postJson($path, [
            'country' => 'KE',
            'accountType' => $accountType,
            'transactionType' => $transactionType,
        ]);

        if (! ($result['ok'] ?? false)) {
            return [
                'items' => [],
                'error' => trim((string) ($result['message'] ?? 'getCountryBanks failed'))." ({$accountType}/{$transactionType})",
            ];
        }

        $data = $result['data'] ?? [];
        $items = [];
        if (is_array($data)) {
            if (array_is_list($data)) {
                $items = $data;
            } elseif (isset($data['banks']) && is_array($data['banks'])) {
                $items = $data['banks'];
            } elseif (isset($data['countryBanks']) && is_array($data['countryBanks'])) {
                $items = $data['countryBanks'];
            } elseif (isset($data['data']) && is_array($data['data'])) {
                $items = array_is_list($data['data']) ? $data['data'] : [];
            }
        }

        $normalized = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $normalized[] = $item;
            }
        }

        return ['items' => $normalized, 'error' => null];
    }

    /**
     * Soft probe — many Cashwyre bill catalogs are country-scoped; failure means hide in app.
     *
     * @return array<string, mixed>
     */
    protected function probeAirtimeCatalog(): array
    {
        // Prefer documented info endpoints if configured; otherwise leave false.
        $path = trim((string) config('cashwyre.paths.airtime_info', ''));
        if ($path === '') {
            return ['ok' => false, 'bills_ok' => false, 'message' => 'No airtime/bills probe path configured.'];
        }

        $result = $this->http->postJson($path, [
            'country' => 'KE',
            'currency' => 'KES',
        ]);

        return [
            'ok' => (bool) ($result['ok'] ?? false),
            'bills_ok' => (bool) ($result['ok'] ?? false),
            'message' => $result['message'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function looksLikeMobileMoney(array $row): bool
    {
        $hay = strtoupper(trim(implode(' ', array_filter([
            (string) ($row['code'] ?? ''),
            (string) ($row['bankCode'] ?? ''),
            (string) ($row['name'] ?? ''),
            (string) ($row['bankName'] ?? ''),
        ]))));

        if ($hay === '') {
            return false;
        }

        foreach (['MPESA', 'M-PESA', 'SAFARICOM', 'AIRTEL', 'MOBILE MONEY', 'MMO'] as $needle) {
            if (str_contains($hay, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function looksLikeMpesa(array $row): bool
    {
        $hay = strtoupper(trim(implode(' ', array_filter([
            (string) ($row['code'] ?? ''),
            (string) ($row['bankCode'] ?? ''),
            (string) ($row['name'] ?? ''),
            (string) ($row['bankName'] ?? ''),
        ]))));

        return str_contains($hay, 'MPESA')
            || str_contains($hay, 'M-PESA')
            || str_contains($hay, 'SAFARICOM');
    }
}
