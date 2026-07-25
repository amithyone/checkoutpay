<?php

namespace App\Services\Consumer;

use App\Models\WhatsappWallet;
use App\Support\Imunify360Ops;
use App\Services\MevonPay\PrivateAccountProvisionService;
use App\Services\Whatsapp\PhoneNormalizer;
use App\Services\Whatsapp\WhatsappWalletCountryResolver;
use Illuminate\Support\Facades\Log;

/**
 * Tier-2 KYC for mobile: Nigeria Rubies VA, Kenya Smile ID National ID.
 */
class ConsumerWalletKycService
{
    public function __construct(
        private PrivateAccountProvisionService $provision,
        private WhatsappWalletCountryResolver $walletCountry,
        private KenyaKycVerificationService $kenyaKyc,
    ) {}

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function tier2Status(WhatsappWallet $wallet): array
    {
        $iso = $this->walletCountry->countryIsoForPhoneE164((string) $wallet->phone_e164);
        $mode = match ($iso) {
            'KE' => 'kenya_smile',
            'NG' => 'nigeria_rubies',
            default => 'unsupported',
        };

        $hasAccount = trim((string) $wallet->mevon_virtual_account_number) !== '';
        $provisionStatus = (string) ($wallet->private_account_provision_status ?? '');
        $pendingAccount = $wallet->isTier2()
            && ! $hasAccount
            && in_array($provisionStatus, [
                PrivateAccountProvisionService::STATUS_QUEUED,
                PrivateAccountProvisionService::STATUS_PROCESSING,
            ], true);

        return [
            'ok' => true,
            'message' => 'OK',
            'data' => [
                'tier' => (int) $wallet->tier,
                'is_tier2' => $wallet->isTier2(),
                'has_permanent_account' => $hasAccount,
                'kyc_pending_account' => $pendingAccount,
                'provision_status' => $provisionStatus !== '' ? $provisionStatus : ($hasAccount ? 'completed' : null),
                'provision_error' => Imunify360Ops::sanitizeConsumerMessage($wallet->private_account_provision_error),
                'provision_queued_at' => $wallet->private_account_provision_queued_at?->toIso8601String(),
                'rubies_account_type' => $wallet->rubies_account_type,
                'kyc_fname' => $wallet->kyc_fname,
                'kyc_lname' => $wallet->kyc_lname,
                'kyc_email' => $wallet->kyc_email,
                'kyc_dob' => $wallet->kyc_dob?->format('Y-m-d'),
                'kyc_cac' => $wallet->kyc_cac,
                'kyc_id_type' => $this->nigeriaKycIdType($wallet),
                'country_iso' => $iso,
                'kyc_mode' => $mode,
                'kenya_tier2_enabled' => $iso === 'KE' ? $this->kenyaKyc->isReady() : false,
                'tier2_available' => match ($iso) {
                    'NG' => true,
                    'KE' => $this->kenyaKyc->isReady(),
                    default => false,
                },
            ],
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function submitPersonalTier2(WhatsappWallet $wallet, array $input): array
    {
        $iso = $this->walletCountry->countryIsoForPhoneE164((string) $wallet->phone_e164);

        if ($iso === 'KE') {
            return $this->kenyaKyc->submitPersonalTier2($wallet, $input);
        }

        if (! $this->walletCountry->isNigeriaPayInWallet((string) $wallet->phone_e164)) {
            return ['ok' => false, 'message' => 'Tier 2 is only available for Nigeria and Kenya wallet numbers.'];
        }

        if ($wallet->tier >= WhatsappWallet::TIER_RUBIES_VA && $wallet->mevon_virtual_account_number) {
            return ['ok' => true, 'message' => 'Already on Tier 2.', 'data' => $this->vaPayload($wallet)];
        }

        $provisionStatus = (string) ($wallet->private_account_provision_status ?? '');
        if (in_array($provisionStatus, [
            PrivateAccountProvisionService::STATUS_QUEUED,
            PrivateAccountProvisionService::STATUS_PROCESSING,
        ], true)) {
            return [
                'ok' => true,
                'message' => 'Account is being created. Check back shortly.',
                'data' => $this->vaPayload($wallet),
            ];
        }

        $fname = trim((string) ($input['fname'] ?? ''));
        $lname = trim((string) ($input['lname'] ?? ''));
        $dob = (string) ($input['dob'] ?? '');
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $bvn = preg_replace('/\D+/', '', (string) ($input['bvn'] ?? '')) ?? '';
        $nin = preg_replace('/\D+/', '', (string) ($input['nin'] ?? '')) ?? '';

        $apiPhone = PhoneNormalizer::e164DigitsToNgLocal11((string) $wallet->phone_e164);
        if ($apiPhone === null) {
            return ['ok' => false, 'message' => 'Could not read wallet phone number.'];
        }

        $gender = strtolower(trim((string) ($input['gender'] ?? '')));
        if (! in_array($gender, ['male', 'female'], true)) {
            return ['ok' => false, 'message' => 'Gender is required (male or female).'];
        }

        $useBvn = strlen($bvn) === 11;
        $useNin = ! $useBvn && strlen($nin) === 11;
        if (! $useBvn && ! $useNin) {
            return ['ok' => false, 'message' => 'BVN or NIN (11 digits) is required.'];
        }

        $payload = [
            'fname' => $fname,
            'lname' => $lname,
            'dob' => $dob,
            'email' => $email,
            'gender' => $gender,
            'bvn' => $useBvn ? $bvn : null,
            'nin' => $useNin ? $nin : null,
        ];

        $result = $this->provision->dispatchPersonalIfReady($wallet, $payload);
        if (! $result['dispatched']) {
            Log::warning('consumer_wallet.kyc.personal_queue_failed', [
                'wallet_id' => $wallet->id,
                'message' => $result['message'],
            ]);

            return ['ok' => false, 'message' => $result['message']];
        }

        return [
            'ok' => true,
            'message' => $result['message'],
            'data' => $this->vaPayload($wallet->fresh()),
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function submitBusinessTier2(WhatsappWallet $wallet, array $input): array
    {
        if (! $this->walletCountry->isNigeriaPayInWallet((string) $wallet->phone_e164)) {
            return ['ok' => false, 'message' => 'Business Tier 2 is only available for Nigeria numbers.'];
        }
        if ($wallet->tier >= WhatsappWallet::TIER_RUBIES_VA && $wallet->mevon_virtual_account_number) {
            return ['ok' => true, 'message' => 'Already on Tier 2.', 'data' => $this->vaPayload($wallet)];
        }

        $provisionStatus = (string) ($wallet->private_account_provision_status ?? '');
        if (in_array($provisionStatus, [
            PrivateAccountProvisionService::STATUS_QUEUED,
            PrivateAccountProvisionService::STATUS_PROCESSING,
        ], true)) {
            return [
                'ok' => true,
                'message' => 'Account is being created. Check back shortly.',
                'data' => $this->vaPayload($wallet),
            ];
        }

        $cac = strtoupper(trim((string) ($input['cac'] ?? '')));
        $dob = (string) ($input['dob'] ?? '');
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $bvn = preg_replace('/\D+/', '', (string) ($input['bvn'] ?? '')) ?? '';
        $fname = trim((string) ($input['fname'] ?? $wallet->kyc_fname ?? ''));
        $lname = trim((string) ($input['lname'] ?? $wallet->kyc_lname ?? ''));

        $result = $this->provision->dispatchPersonalBusinessIfReady($wallet, [
            'cac' => $cac,
            'dob' => $dob,
            'email' => $email,
            'bvn' => $bvn,
            'fname' => $fname,
            'lname' => $lname,
            'business_name' => trim((string) ($input['business_name'] ?? $cac)),
        ]);

        if (! $result['dispatched']) {
            Log::warning('consumer_wallet.kyc.business_queue_failed', [
                'wallet_id' => $wallet->id,
                'message' => $result['message'],
            ]);

            return ['ok' => false, 'message' => $result['message']];
        }

        return [
            'ok' => true,
            'message' => $result['message'],
            'data' => $this->vaPayload($wallet->fresh()),
        ];
    }

    private function nigeriaKycIdType(WhatsappWallet $wallet): ?string
    {
        $bvn = preg_replace('/\D+/', '', (string) $wallet->kyc_bvn) ?? '';
        if (strlen($bvn) === 11) {
            return 'bvn';
        }
        $nin = preg_replace('/\D+/', '', (string) $wallet->kyc_nin) ?? '';
        if (strlen($nin) === 11) {
            return 'nin';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function vaPayload(WhatsappWallet $wallet): array
    {
        $hasAccount = trim((string) $wallet->mevon_virtual_account_number) !== '';
        $provisionStatus = (string) ($wallet->private_account_provision_status ?? '');

        return [
            'account_number' => $wallet->mevon_virtual_account_number,
            'bank_name' => $wallet->mevon_bank_name,
            'bank_code' => $wallet->mevon_bank_code,
            'reference' => $wallet->mevon_reference,
            'is_tier2' => $wallet->isTier2(),
            'provision_status' => $provisionStatus !== '' ? $provisionStatus : ($hasAccount ? 'completed' : null),
            'provision_error' => Imunify360Ops::sanitizeConsumerMessage($wallet->private_account_provision_error),
            'kyc_pending_account' => $wallet->isTier2() && ! $hasAccount && in_array($provisionStatus, [
                PrivateAccountProvisionService::STATUS_QUEUED,
                PrivateAccountProvisionService::STATUS_PROCESSING,
            ], true),
            'kyc_mode' => 'nigeria_rubies',
        ];
    }
}
