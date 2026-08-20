<?php

namespace App\Services\Consumer;

use App\Models\Setting;
use App\Models\WhatsappWallet;
use App\Services\SmileId\SmileIdBasicKycClient;
use App\Services\Whatsapp\PhoneNormalizer;
use App\Support\WhatsappWalletKycInputGuard;

/**
 * Kenya Tier 2 via Smile ID Basic KYC (National ID).
 */
final class KenyaKycVerificationService
{
    public function __construct(
        private SmileIdBasicKycClient $smile,
    ) {}

    public function isEnabled(): bool
    {
        $stored = Setting::get('kenya_tier2_enabled');
        if ($stored !== null) {
            return (bool) $stored;
        }

        return (bool) config('consumer_wallet.kenya_tier2_enabled', false);
    }

    public function isReady(): bool
    {
        return $this->isEnabled() && $this->smile->isConfigured();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function submitPersonalTier2(WhatsappWallet $wallet, array $input): array
    {
        if (! $this->isEnabled()) {
            return ['ok' => false, 'message' => 'Kenya Tier 2 verification is not enabled yet.'];
        }
        if (! $this->smile->isConfigured()) {
            return ['ok' => false, 'message' => 'Kenya identity verification is not configured (Smile ID).'];
        }

        if ($wallet->isTier2() && $wallet->kyc_verified_at !== null) {
            return [
                'ok' => true,
                'message' => 'Already on Tier 2.',
                'data' => [
                    'account_number' => null,
                    'bank_name' => null,
                    'bank_code' => null,
                    'reference' => null,
                    'kyc_mode' => 'kenya_smile',
                ],
            ];
        }

        $fname = trim((string) ($input['fname'] ?? ''));
        $lname = trim((string) ($input['lname'] ?? ''));
        $dob = (string) ($input['dob'] ?? '');
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $nationalId = preg_replace('/\D+/', '', (string) ($input['national_id'] ?? '')) ?? '';
        $gender = strtolower(trim((string) ($input['gender'] ?? '')));

        if (strlen($fname) < 2 || strlen($lname) < 2) {
            return ['ok' => false, 'message' => 'Enter your legal first and last name.'];
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
            return ['ok' => false, 'message' => 'Date of birth must be YYYY-MM-DD.'];
        }
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Enter a valid email address.'];
        }
        if ($emailErr = WhatsappWalletKycInputGuard::emailError($email)) {
            return ['ok' => false, 'message' => $emailErr];
        }
        if (! in_array($gender, ['male', 'female'], true)) {
            return ['ok' => false, 'message' => 'Gender is required (male or female).'];
        }
        if (strlen($nationalId) < 5 || strlen($nationalId) > 12) {
            return ['ok' => false, 'message' => 'Enter a valid Kenya National ID number.'];
        }

        $localPhone = PhoneNormalizer::e164DigitsToLocalTrunk((string) $wallet->phone_e164)
            ?? PhoneNormalizer::digitsOnly((string) $wallet->phone_e164);

        $result = $this->smile->basicKyc([
            'country' => 'KE',
            'id_type' => 'NATIONAL_ID',
            'id_number' => $nationalId,
            'first_name' => $fname,
            'last_name' => $lname,
            'dob' => $dob,
            'gender' => $gender,
            'phone_number' => $localPhone,
            'user_id' => 'wallet-'.$wallet->id,
        ]);

        if (! ($result['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string) ($result['message'] ?? 'Verification failed.')];
        }
        if (! ($result['matched'] ?? false)) {
            return ['ok' => false, 'message' => (string) ($result['message'] ?? 'Identity could not be verified.')];
        }

        $wallet->update([
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'rubies_account_type' => 'personal',
            'kyc_cac' => null,
            'kyc_fname' => $fname,
            'kyc_lname' => $lname,
            'kyc_gender' => $gender,
            'kyc_dob' => $dob,
            'kyc_bvn' => null,
            'kyc_nin' => null,
            'kyc_national_id' => $nationalId,
            'kyc_email' => $email,
            'kyc_verified_at' => now(),
            'tier2_provisioned_at' => now(),
            'sender_name' => trim($fname.' '.$lname),
        ]);

        return [
            'ok' => true,
            'message' => 'Tier 2 activated.',
            'data' => [
                'account_number' => null,
                'bank_name' => null,
                'bank_code' => null,
                'reference' => $result['smile_job_id'] ?? null,
                'kyc_mode' => 'kenya_smile',
                'result_code' => $result['result_code'] ?? null,
            ],
        ];
    }
}
