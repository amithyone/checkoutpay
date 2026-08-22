<?php

namespace App\Services\WalletImport;

use App\Models\WhatsappWallet;
use App\Services\Consumer\ConsumerWalletPayCodeService;
use App\Services\MevonPay\PrivateAccountProvisionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Seed sterilized form JSONL rows into whatsapp_wallets.
 */
final class FormCsvWalletSeedService
{
    public function __construct(
        private ConsumerWalletPayCodeService $payCodes,
        private PrivateAccountProvisionService $provision,
    ) {}

    /**
     * @return array{
     *   would_create: int,
     *   created: int,
     *   skipped_existing: int,
     *   skipped_reject: int,
     *   skipped_needs_review: int,
     *   provision_queued: int,
     *   provision_failed: int,
     *   errors: list<string>
     * }
     */
    public function seedFromJsonl(
        string $jsonlPath,
        bool $apply,
        bool $onlyOk = true,
        bool $provisionTier2 = false,
    ): array {
        if (! is_readable($jsonlPath)) {
            throw new \InvalidArgumentException('JSONL not readable: '.$jsonlPath);
        }

        $stats = [
            'would_create' => 0,
            'created' => 0,
            'skipped_existing' => 0,
            'skipped_reject' => 0,
            'skipped_needs_review' => 0,
            'provision_queued' => 0,
            'provision_failed' => 0,
            'errors' => [],
        ];

        $fh = fopen($jsonlPath, 'rb');
        if ($fh === false) {
            throw new \RuntimeException('Could not open '.$jsonlPath);
        }

        try {
            $lineNo = 0;
            while (($line = fgets($fh)) !== false) {
                $lineNo++;
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $row = json_decode($line, true);
                if (! is_array($row)) {
                    $stats['errors'][] = "line {$lineNo}: invalid JSON";
                    continue;
                }

                $status = (string) ($row['status'] ?? 'reject');
                if ($status === 'reject') {
                    $stats['skipped_reject']++;
                    continue;
                }
                if ($onlyOk && $status !== 'ok') {
                    $stats['skipped_needs_review']++;
                    continue;
                }

                $phone = (string) ($row['phone_e164'] ?? '');
                if ($phone === '' || ! preg_match('/^234\d{10}$/', $phone)) {
                    $stats['skipped_reject']++;
                    $stats['errors'][] = "line {$lineNo}: bad phone";
                    continue;
                }

                $fname = trim((string) ($row['kyc_fname'] ?? ''));
                $lname = trim((string) ($row['kyc_lname'] ?? ''));
                $full = trim((string) ($row['confirmed_account_name'] ?? ($fname.' '.$lname)));
                if ($fname === '' && $lname === '' && $full === '') {
                    $stats['skipped_reject']++;
                    $stats['errors'][] = "line {$lineNo}: empty name";
                    continue;
                }

                $existing = WhatsappWallet::query()->where('phone_e164', $phone)->first();
                if ($existing !== null) {
                    $stats['skipped_existing']++;
                    continue;
                }

                $stats['would_create']++;
                if (! $apply) {
                    continue;
                }

                $bvn = isset($row['bvn']) && is_string($row['bvn']) && strlen($row['bvn']) === 11
                    ? $row['bvn']
                    : null;

                $address = isset($row['address']) ? trim((string) $row['address']) : '';
                $wallet = WhatsappWallet::query()->create([
                    'phone_e164' => $phone,
                    'status' => WhatsappWallet::STATUS_ACTIVE,
                    'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
                    'balance' => 0,
                    'kyc_fname' => $fname !== '' ? $fname : null,
                    'kyc_lname' => $lname !== '' ? $lname : null,
                    'sender_name' => $full !== '' ? $full : null,
                    'kyc_gender' => $row['gender'] ?? null,
                    'kyc_dob' => $row['dob'] ?? null,
                    'kyc_email' => $row['email'] ?? null,
                    'kyc_bvn' => $bvn,
                    'card_home_address' => $address !== '' ? Str::limit($address, 500, '') : null,
                ]);

                $this->payCodes->ensureForWallet($wallet);
                $stats['created']++;

                if ($provisionTier2 && (int) ($row['tier_target'] ?? 1) === 2 && $bvn !== null) {
                    $result = $this->tryProvisionTier2($wallet->fresh() ?? $wallet);
                    if ($result['ok']) {
                        $stats['provision_queued']++;
                    } else {
                        $stats['provision_failed']++;
                        $stats['errors'][] = "wallet {$wallet->id}: ".$result['message'];
                    }
                }
            }
        } finally {
            fclose($fh);
        }

        return $stats;
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function tryProvisionTier2(WhatsappWallet $wallet): array
    {
        $result = $this->provision->dispatchPersonalFromStoredKyc($wallet, false);
        if (! empty($result['dispatched'])) {
            Log::info('wallet_import.tier2_provision_queued', [
                'wallet_id' => $wallet->id,
                'phone' => $wallet->phone_e164,
            ]);

            return ['ok' => true, 'message' => (string) ($result['message'] ?? 'queued')];
        }

        return [
            'ok' => false,
            'message' => (string) ($result['message'] ?? 'provision failed'),
        ];
    }
}
