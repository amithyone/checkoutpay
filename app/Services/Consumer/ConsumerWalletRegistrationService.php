<?php

namespace App\Services\Consumer;

use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use App\Services\Whatsapp\PhoneNormalizer;
use App\Services\Whatsapp\WhatsappWalletCountryResolver;
use App\Support\WhatsappWalletKycInputGuard;
use Illuminate\Support\Facades\DB;

class ConsumerWalletRegistrationService
{
    public function __construct(
        private ConsumerWalletOtpService $otp,
        private WalletReferralAttributionService $referrals,
        private WalletKycComplianceService $kyc,
        private WhatsappWalletCountryResolver $walletCountry,
    ) {}

    /**
     * @param  array{fname: string, lname: string, email: string, bvn?: string|null, nin?: string|null, dob?: string|null, gender?: string|null, referral_code?: string|null, country?: string|null}  $profile
     * @return array{ok: bool, message: string, phone_e164?: string, token?: string, token_type?: string, wallet_id?: int, error_code?: string, http_status?: int, kyc?: array<string, mixed>}
     */
    public function register(string $phoneInput, string $code, array $profile): array
    {
        $e164 = PhoneNormalizer::canonicalAuthE164Digits(
            $phoneInput,
            isset($profile['country']) ? (string) $profile['country'] : null,
        );
        if ($e164 === null) {
            return ['ok' => false, 'message' => 'Invalid mobile number for a supported country.'];
        }
        if ($phoneErr = WhatsappWalletKycInputGuard::phoneError($e164)) {
            return ['ok' => false, 'message' => $phoneErr];
        }

        $fname = trim((string) ($profile['fname'] ?? ''));
        $lname = trim((string) ($profile['lname'] ?? ''));
        $email = strtolower(trim((string) ($profile['email'] ?? '')));

        if ($emailErr = WhatsappWalletKycInputGuard::emailError($email)) {
            return ['ok' => false, 'message' => $emailErr, 'error_code' => 'kyc_incomplete'];
        }

        $bvn = $this->digitsOrNull($profile['bvn'] ?? null, 11);
        $nin = $this->digitsOrNull($profile['nin'] ?? null, 11);
        if ($bvn !== null && strlen($bvn) !== 11) {
            return ['ok' => false, 'message' => 'BVN must be 11 digits when provided.', 'error_code' => 'kyc_incomplete'];
        }
        if ($nin !== null && strlen($nin) !== 11) {
            return ['ok' => false, 'message' => 'NIN must be 11 digits when provided.', 'error_code' => 'kyc_incomplete'];
        }
        if ($bvn !== null && ($bvnErr = WhatsappWalletKycInputGuard::bvnOrNinError($bvn, 'BVN'))) {
            return ['ok' => false, 'message' => $bvnErr, 'error_code' => 'kyc_incomplete'];
        }
        if ($nin !== null && ($ninErr = WhatsappWalletKycInputGuard::bvnOrNinError($nin, 'NIN'))) {
            return ['ok' => false, 'message' => $ninErr, 'error_code' => 'kyc_incomplete'];
        }

        $dob = trim((string) ($profile['dob'] ?? ''));
        if ($dob !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
            return ['ok' => false, 'message' => 'Date of birth must be YYYY-MM-DD when provided.', 'error_code' => 'kyc_incomplete'];
        }

        $gender = strtolower(trim((string) ($profile['gender'] ?? '')));
        if ($gender !== '' && ! in_array($gender, ['male', 'female', 'm', 'f'], true)) {
            return ['ok' => false, 'message' => 'Select a valid gender when provided.', 'error_code' => 'kyc_incomplete'];
        }
        if (in_array($gender, ['m', 'f'], true)) {
            $gender = $gender === 'm' ? 'male' : 'female';
        }

        $isNg = $this->walletCountry->isNigeriaPayInWallet($e164);
        $failClosed = $this->kyc->failClosedEnabled() && $isNg;

        if ($failClosed) {
            if ($err = $this->kyc->legalNameError($fname, $lname)) {
                return ['ok' => false, 'message' => $err, 'error_code' => 'kyc_incomplete'];
            }
            if ($dob === '') {
                return ['ok' => false, 'message' => 'Date of birth is required.', 'error_code' => 'kyc_incomplete'];
            }
            if (! in_array($gender, ['male', 'female'], true)) {
                return ['ok' => false, 'message' => 'Gender is required (male or female).', 'error_code' => 'kyc_incomplete'];
            }
            if (($bvn === null || $bvn === '') && ($nin === null || $nin === '')) {
                return ['ok' => false, 'message' => 'BVN or NIN is required.', 'error_code' => 'kyc_incomplete'];
            }
        } else {
            if (strlen($fname) < 2) {
                return ['ok' => false, 'message' => 'Enter your first name.'];
            }
            if (strlen($lname) < 2) {
                return ['ok' => false, 'message' => 'Enter your last name.'];
            }
        }

        $existing = WhatsappWallet::query()->where('phone_e164', $e164)->first();
        if ($existing && ! $existing->needsRegistrationProfile()) {
            return ['ok' => false, 'message' => 'This number already has a wallet. Sign in instead.', 'error_code' => 'duplicate_identity', 'http_status' => 409];
        }

        if ($failClosed) {
            $dup = $this->kyc->assertUniqueIdentity($existing, $bvn, $nin, $email, $e164);
            if (! $dup['ok']) {
                return $dup;
            }
        }

        $verified = $this->otp->verifyOtp($phoneInput, $code);
        if (! $verified['ok']) {
            return ['ok' => false, 'message' => $verified['message']];
        }

        $referralCode = trim((string) ($profile['referral_code'] ?? ''));

        $created = DB::transaction(function () use ($e164, $fname, $lname, $email, $bvn, $nin, $dob, $gender, $referralCode, $failClosed) {
            $wallet = WhatsappWallet::query()->firstOrCreate(
                ['phone_e164' => $e164],
                [
                    'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
                    'balance' => 0,
                    'status' => WhatsappWallet::STATUS_ACTIVE,
                ]
            );

            $wallet->kyc_fname = $fname;
            $wallet->kyc_lname = $lname;
            $wallet->kyc_email = $email;
            if ($dob !== '') {
                $wallet->kyc_dob = $dob;
            }
            if ($gender !== '') {
                $wallet->kyc_gender = $gender;
            }
            if ($failClosed) {
                $wallet->kyc_status = WhatsappWallet::KYC_STATUS_INCOMPLETE;
            } else {
                if ($bvn !== null && $bvn !== '') {
                    $wallet->kyc_bvn = $bvn;
                }
                if ($nin !== null && $nin !== '') {
                    $wallet->kyc_nin = $nin;
                }
            }
            if ($wallet->normalizedSenderName() === null) {
                $wallet->sender_name = trim($fname.' '.$lname);
            }
            $wallet->save();

            if ($referralCode !== '') {
                $this->referrals->attributeFromRegistration($wallet->fresh(), $referralCode);
            }

            $account = ConsumerWalletApiAccount::query()->firstOrNew(['phone_e164' => $e164]);
            $account->whatsapp_wallet_id = $wallet->id;
            $account->phone_e164 = $e164;
            $account->save();

            $account->tokens()->delete();
            $plain = app(ConsumerAppSessionService::class)->createAccessToken($account)->plainTextToken;

            return [
                'wallet' => $wallet->fresh(),
                'token' => $plain,
            ];
        });

        /** @var WhatsappWallet $wallet */
        $wallet = $created['wallet'];
        $identityResult = null;
        if ($failClosed) {
            $identityResult = $this->kyc->submitIdentity($wallet, [
                'fname' => $fname,
                'lname' => $lname,
                'email' => $email,
                'dob' => $dob,
                'gender' => $gender,
                'bvn' => $bvn,
                'nin' => $nin,
            ]);
            $wallet = $wallet->fresh();
        }

        $out = [
            'ok' => true,
            'message' => 'Account created.',
            'phone_e164' => $e164,
            'token' => $created['token'],
            'token_type' => 'Bearer',
            'wallet_id' => $wallet->id,
            'kyc' => $this->kyc->payload($wallet),
        ];

        if (is_array($identityResult) && ! ($identityResult['ok'] ?? true)) {
            $out['message'] = (string) ($identityResult['message'] ?? $out['message']);
            $out['error_code'] = $identityResult['error_code'] ?? 'account_restricted';
            $out['kyc'] = $identityResult['data'] ?? $out['kyc'];
        }

        return $out;
    }

    private function digitsOrNull(mixed $value, int $length): ?string
    {
        if ($value === null) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
        if ($digits === '') {
            return null;
        }

        return strlen($digits) === $length ? $digits : $digits;
    }
}
