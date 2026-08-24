<?php

namespace App\Services\WalletImport;

use App\Models\WhatsappWallet;
use App\Services\MevonPay\PrivateAccountProvisionService;
use Illuminate\Support\Facades\Log;

/**
 * Gradually queue Mevon Tier-2 personal VA creation for imported (or KYC-ready) wallets.
 */
final class FormCsvTier2BatchProvisionService
{
    public function __construct(
        private PrivateAccountProvisionService $provision,
    ) {}

    /**
     * @return array{
     *   candidates: int,
     *   attempted: int,
     *   queued: int,
     *   skipped_not_ready: int,
     *   skipped_has_va: int,
     *   skipped_in_flight: int,
     *   failed: int,
     *   details: list<array{phone: string, wallet_id: int|null, result: string, message?: string}>
     * }
     */
    public function run(
        int $limit = 8,
        bool $apply = false,
        ?string $jsonlPath = null,
    ): array {
        $limit = max(1, min(50, $limit));
        $stats = [
            'candidates' => 0,
            'attempted' => 0,
            'queued' => 0,
            'skipped_not_ready' => 0,
            'skipped_has_va' => 0,
            'skipped_in_flight' => 0,
            'failed' => 0,
            'details' => [],
        ];

        $phones = $this->candidatePhones($jsonlPath);
        $stats['candidates'] = count($phones);

        foreach ($phones as $phone) {
            if ($stats['attempted'] >= $limit) {
                break;
            }

            $wallet = WhatsappWallet::query()->where('phone_e164', $phone)->first();
            if ($wallet === null) {
                continue;
            }

            if (trim((string) $wallet->mevon_virtual_account_number) !== '') {
                $stats['skipped_has_va']++;
                continue;
            }

            $status = (string) ($wallet->private_account_provision_status ?? '');
            $inFlight = [
                PrivateAccountProvisionService::STATUS_QUEUED,
                PrivateAccountProvisionService::STATUS_PROCESSING,
                PrivateAccountProvisionService::STATUS_COMPLETED,
            ];
            if (! (bool) config('services.mevonpay.tier2_batch_include_failed', false)) {
                $inFlight[] = PrivateAccountProvisionService::STATUS_FAILED;
            }
            if (in_array($status, $inFlight, true)) {
                $stats['skipped_in_flight']++;
                continue;
            }

            $readiness = $this->provision->personalReadiness($wallet);
            if (! ($readiness['ready'] ?? false)) {
                $stats['skipped_not_ready']++;
                $stats['details'][] = [
                    'phone' => $phone,
                    'wallet_id' => (int) $wallet->id,
                    'result' => 'not_ready',
                    'message' => implode(' ', $readiness['missing'] ?? []),
                ];
                continue;
            }

            $stats['attempted']++;

            if (! $apply) {
                $stats['details'][] = [
                    'phone' => $phone,
                    'wallet_id' => (int) $wallet->id,
                    'result' => 'would_queue',
                ];
                continue;
            }

            $result = $this->provision->dispatchPersonalFromStoredKyc($wallet, false);
            if (! empty($result['dispatched'])) {
                $stats['queued']++;
                $stats['details'][] = [
                    'phone' => $phone,
                    'wallet_id' => (int) $wallet->id,
                    'result' => 'queued',
                    'message' => (string) ($result['message'] ?? ''),
                ];
                Log::info('wallet_import.tier2_batch_queued', [
                    'wallet_id' => $wallet->id,
                    'phone' => $phone,
                ]);
            } else {
                $stats['failed']++;
                $stats['details'][] = [
                    'phone' => $phone,
                    'wallet_id' => (int) $wallet->id,
                    'result' => 'failed',
                    'message' => (string) ($result['message'] ?? 'dispatch failed'),
                ];
            }
        }

        return $stats;
    }

    /**
     * Prefer sterilized JSONL tier_target=2 phones (stable import cohort), else DB fallback.
     *
     * @return list<string>
     */
    private function candidatePhones(?string $jsonlPath): array
    {
        $path = $jsonlPath
            ?: base_path('database/backups/imports/sterilized/form-responses-sterilized.jsonl');

        $fromFile = [];
        if (is_readable($path)) {
            $fh = fopen($path, 'rb');
            if ($fh !== false) {
                try {
                    while (($line = fgets($fh)) !== false) {
                        $line = trim($line);
                        if ($line === '') {
                            continue;
                        }
                        $row = json_decode($line, true);
                        if (! is_array($row)) {
                            continue;
                        }
                        if (($row['status'] ?? '') !== 'ok') {
                            continue;
                        }
                        if ((int) ($row['tier_target'] ?? 1) !== 2) {
                            continue;
                        }
                        $phone = (string) ($row['phone_e164'] ?? '');
                        if (preg_match('/^234\d{10}$/', $phone)) {
                            $fromFile[$phone] = true;
                        }
                    }
                } finally {
                    fclose($fh);
                }
            }
        }

        if ($fromFile !== []) {
            return array_keys($fromFile);
        }

        // Fallback: any Tier-1 wallet with BVN and no VA yet.
        return WhatsappWallet::query()
            ->where('tier', WhatsappWallet::TIER_WHATSAPP_ONLY)
            ->whereNotNull('kyc_bvn')
            ->where(function ($q) {
                $q->whereNull('mevon_virtual_account_number')
                    ->orWhere('mevon_virtual_account_number', '');
            })
            ->orderBy('id')
            ->limit(2000)
            ->pluck('phone_e164')
            ->map(fn ($p) => (string) $p)
            ->filter(fn (string $p) => preg_match('/^234\d{10}$/', $p) === 1)
            ->values()
            ->all();
    }
}
