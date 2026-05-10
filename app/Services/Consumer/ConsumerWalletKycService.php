<?php

namespace App\Services\Consumer;

use App\Models\WhatsappWallet;
use App\Services\MevonRubiesVirtualAccountService;
use App\Services\Whatsapp\PhoneNormalizer;
use App\Services\Whatsapp\WhatsappWalletCountryResolver;
use Illuminate\Support\Facades\Log;

/**
 * Tier-2 Rubies KYC for mobile (same Mevon calls as WhatsApp upgrade flow).
 */
class ConsumerWalletKycService
{
    public function __construct(
        private MevonRubiesVirtualAccountService $rubies,
        private WhatsappWalletCountryResolver $walletCountry,
    ) {}

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function tier2Status(WhatsappWallet $wallet): array
    {
        return [
            'ok' => true,
            'message' => 'OK',
            'data' => [
                'tier' => (int) $wallet->tier,
                'is_tier2' => $wallet->isTier2(),
                'has_permanent_account' => $wallet->isTier2() && trim((string) $wallet->mevon_virtual_account_number) !== '',
                'rubies_account_type' => $wallet->rubies_account_type,
                'kyc_fname' => $wallet->kyc_fname,
                'kyc_lname' => $wallet->kyc_lname,
                'kyc_email' => $wallet->kyc_email,
                'kyc_cac' => $wallet->kyc_cac,
            ],
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function submitPersonalTier2(WhatsappWallet $wallet, array $input): array
    {
        if (! $this->walletCountry->isNigeriaPayInWallet((string) $wallet->phone_e164)) {
            return ['ok' => false, 'message' => 'Tier 2 is only available for Nigeria numbers.'];
        }
        if ($wallet->tier >= WhatsappWallet::TIER_RUBIES_VA && $wallet->mevon_virtual_account_number) {
            return ['ok' => true, 'message' => 'Already on Tier 2.', 'data' => $this->vaPayload($wallet)];
        }
        if (! $this->rubies->isConfigured()) {
            return ['ok' => false, 'message' => 'Tier 2 provisioning is not configured.'];
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

        try {
            $created = strlen($bvn) === 11
                ? $this->rubies->createRubiesPersonalAccount($fname, $lname, $apiPhone, $dob, $email, $bvn, null)
                : $this->rubies->createRubiesPersonalAccount($fname, $lname, $apiPhone, $dob, $email, null, strlen($nin) === 11 ? $nin : null);
        } catch (\Throwable $e) {
            Log::warning('consumer_wallet.kyc.personal_failed', ['wallet_id' => $wallet->id, 'error' => $e->getMessage()]);

            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $gender = strtolower(trim((string) ($input['gender'] ?? '')));
        if (! in_array($gender, ['male', 'female'], true)) {
            $gender = '';
        }

        $wallet->update([
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'rubies_account_type' => 'personal',
            'kyc_cac' => null,
            'kyc_fname' => $fname,
            'kyc_lname' => $lname,
            'kyc_gender' => $gender,
            'kyc_dob' => $dob,
            'kyc_bvn' => strlen($bvn) === 11 ? $bvn : null,
            'kyc_email' => $email,
            'kyc_verified_at' => now(),
            'mevon_virtual_account_number' => $created['account_number'],
            'mevon_bank_name' => $created['bank_name'],
            'mevon_bank_code' => $created['bank_code'],
            'mevon_reference' => $created['reference'] !== '' ? $created['reference'] : $wallet->mevon_reference,
            'tier2_provisioned_at' => now(),
        ]);

        return ['ok' => true, 'message' => 'Tier 2 activated.', 'data' => $this->vaPayload($wallet->fresh())];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function submitBusinessTier2(WhatsappWallet $wallet, array $input): array
    {
        if (! $this->walletCountry->isNigeriaPayInWallet((string) $wallet->phone_e164)) {
            return ['ok' => false, 'message' => 'Tier 2 is only available for Nigeria numbers.'];
        }
        if ($wallet->tier >= WhatsappWallet::TIER_RUBIES_VA && $wallet->mevon_virtual_account_number) {
            return ['ok' => true, 'message' => 'Already on Tier 2.', 'data' => $this->vaPayload($wallet)];
        }
        if (! $this->rubies->isConfigured()) {
            return ['ok' => false, 'message' => 'Tier 2 provisioning is not configured.'];
        }

        $cac = strtoupper(trim((string) ($input['cac'] ?? '')));
        $dob = (string) ($input['dob'] ?? '');
        $email = strtolower(trim((string) ($input['email'] ?? '')));

        $apiPhone = PhoneNormalizer::e164DigitsToNgLocal11((string) $wallet->phone_e164);
        if ($apiPhone === null) {
            return ['ok' => false, 'message' => 'Could not read wallet phone number.'];
        }

        try {
            $created = $this->rubies->createRubiesBusinessAccount($cac, $apiPhone, $dob, $email);
        } catch (\Throwable $e) {
            Log::warning('consumer_wallet.kyc.business_failed', ['wallet_id' => $wallet->id, 'error' => $e->getMessage()]);

            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $wallet->update([
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'rubies_account_type' => 'business',
            'kyc_cac' => $cac,
            'kyc_fname' => null,
            'kyc_lname' => null,
            'kyc_gender' => null,
            'kyc_dob' => $dob,
            'kyc_bvn' => null,
            'kyc_email' => $email,
            'kyc_verified_at' => now(),
            'mevon_virtual_account_number' => $created['account_number'],
            'mevon_bank_name' => $created['bank_name'],
            'mevon_bank_code' => $created['bank_code'],
            'mevon_reference' => $created['reference'] !== '' ? $created['reference'] : $wallet->mevon_reference,
            'tier2_provisioned_at' => now(),
        ]);

        return ['ok' => true, 'message' => 'Business Tier 2 activated.', 'data' => $this->vaPayload($wallet->fresh())];
    }

    /**
     * @return array<string, mixed>
     */
    private function vaPayload(WhatsappWallet $wallet): array
    {
        return [
            'account_number' => $wallet->mevon_virtual_account_number,
            'bank_name' => $wallet->mevon_bank_name,
            'bank_code' => $wallet->mevon_bank_code,
            'reference' => $wallet->mevon_reference,
        ];
    }
}
