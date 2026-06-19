<?php

namespace App\Services\Consumer;

use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use App\Services\Whatsapp\PhoneNormalizer;
use Illuminate\Support\Facades\DB;

class ConsumerWalletRegistrationService
{
    public function __construct(
        private ConsumerWalletOtpService $otp,
    ) {}

    /**
     * @param  array{fname: string, lname: string, email: string, bvn?: string|null, nin?: string|null, dob?: string|null, gender?: string|null}  $profile
     * @return array{ok: bool, message: string, phone_e164?: string, token?: string, token_type?: string, wallet_id?: int}
     */
    public function register(string $phoneInput, string $code, array $profile): array
    {
        $e164 = PhoneNormalizer::canonicalNgE164Digits($phoneInput);
        if ($e164 === null) {
            return ['ok' => false, 'message' => 'Invalid Nigerian mobile number.'];
        }

        $fname = trim((string) ($profile['fname'] ?? ''));
        $lname = trim((string) ($profile['lname'] ?? ''));
        $email = strtolower(trim((string) ($profile['email'] ?? '')));

        if (strlen($fname) < 2) {
            return ['ok' => false, 'message' => 'Enter your first name.'];
        }
        if (strlen($lname) < 2) {
            return ['ok' => false, 'message' => 'Enter your last name.'];
        }
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Enter a valid email address.'];
        }

        $bvn = $this->digitsOrNull($profile['bvn'] ?? null, 11);
        $nin = $this->digitsOrNull($profile['nin'] ?? null, 11);
        if ($bvn !== null && strlen($bvn) !== 11) {
            return ['ok' => false, 'message' => 'BVN must be 11 digits when provided.'];
        }
        if ($nin !== null && strlen($nin) !== 11) {
            return ['ok' => false, 'message' => 'NIN must be 11 digits when provided.'];
        }

        $dob = trim((string) ($profile['dob'] ?? ''));
        if ($dob !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
            return ['ok' => false, 'message' => 'Date of birth must be YYYY-MM-DD when provided.'];
        }

        $gender = strtolower(trim((string) ($profile['gender'] ?? '')));
        if ($gender !== '' && ! in_array($gender, ['male', 'female', 'm', 'f'], true)) {
            return ['ok' => false, 'message' => 'Select a valid gender when provided.'];
        }
        if (in_array($gender, ['m', 'f'], true)) {
            $gender = $gender === 'm' ? 'male' : 'female';
        }

        $verified = $this->otp->verifyOtp($phoneInput, $code);
        if (! $verified['ok']) {
            return ['ok' => false, 'message' => $verified['message']];
        }

        return DB::transaction(function () use ($e164, $fname, $lname, $email, $bvn, $nin, $dob, $gender) {
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
            if ($bvn !== null && $bvn !== '') {
                $wallet->kyc_bvn = $bvn;
            }
            if ($dob !== '') {
                $wallet->kyc_dob = $dob;
            }
            if ($gender !== '') {
                $wallet->kyc_gender = $gender;
            }
            if ($wallet->normalizedSenderName() === null) {
                $wallet->sender_name = trim($fname.' '.$lname);
            }
            $wallet->save();

            $account = ConsumerWalletApiAccount::query()->firstOrNew(['phone_e164' => $e164]);
            $account->whatsapp_wallet_id = $wallet->id;
            $account->phone_e164 = $e164;
            $account->save();

            $account->tokens()->delete();
            $tokenName = (string) config('consumer_wallet.token_name', 'consumer_mobile');
            $plain = $account->createToken($tokenName)->plainTextToken;

            return [
                'ok' => true,
                'message' => 'Account created.',
                'phone_e164' => $e164,
                'token' => $plain,
                'token_type' => 'Bearer',
                'wallet_id' => $wallet->id,
            ];
        });
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
