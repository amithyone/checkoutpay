<?php

namespace App\Services\Consumer;

use App\Models\KycAuditEvent;
use App\Models\WhatsappWallet;
use App\Services\MevonPay\MevonIdentityVerificationService;
use App\Services\MevonPay\PrivateAccountProvisionService;
use App\Services\Whatsapp\WhatsappWalletCountryResolver;
use App\Support\WhatsappWalletKycInputGuard;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class WalletKycComplianceService
{
    public const ID_TYPES = ['passport', 'nin_slip', 'pvc', 'drivers_licence'];

    public const DOC_PENDING = 'pending';

    public const DOC_ACCEPTED = 'accepted';

    public const DOC_REJECTED = 'rejected';

    public function __construct(
        private MevonIdentityVerificationService $identity,
        private WhatsappWalletCountryResolver $walletCountry,
        private PrivateAccountProvisionService $provision,
    ) {}

    public function failClosedEnabled(): bool
    {
        return (bool) config('consumer_wallet.kyc_fail_closed', false);
    }

    public function appliesTo(WhatsappWallet $wallet): bool
    {
        return $this->failClosedEnabled()
            && $this->walletCountry->isNigeriaPayInWallet((string) $wallet->phone_e164);
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(WhatsappWallet $wallet): array
    {
        $wallet->refresh();
        $this->syncDerivedState($wallet);
        $wallet->refresh();

        $limits = $this->limits($wallet);
        $status = $this->effectiveStatus($wallet);

        return [
            'kyc_status' => $status,
            'kyc_tier' => $this->effectiveTier($wallet),
            'can_transact' => $this->canTransact($wallet),
            'email_verified' => $wallet->kyc_email_verified_at !== null,
            'fail_closed' => $this->failClosedEnabled(),
            'kyc_mode' => $this->walletCountry->isNigeriaPayInWallet((string) $wallet->phone_e164)
                ? 'nigeria_rubies'
                : ($this->walletCountry->countryIsoForPhoneE164((string) $wallet->phone_e164) === 'KE' ? 'kenya_smile' : 'unsupported'),
            'missing_requirements' => $this->missingRequirements($wallet),
            'kyc_limits' => $limits,
            'error_code' => $this->statusErrorCode($status),
            'profile' => [
                'fname' => $wallet->kyc_fname,
                'lname' => $wallet->kyc_lname,
                'dob' => $wallet->kyc_dob?->format('Y-m-d'),
                'gender' => $wallet->kyc_gender,
                'email' => $wallet->kyc_email,
                'has_bvn' => $this->hasVerifiedBvn($wallet) || $this->hasBvnDigits($wallet),
                'has_nin' => $this->hasVerifiedNin($wallet) || $this->hasNinDigits($wallet),
                'id_document_status' => $wallet->kyc_id_document_status,
                'address_status' => $wallet->kyc_address_status,
            ],
        ];
    }

    /**
     * @return array{currency: string, daily_send: float|null, daily_send_used: float, daily_send_remaining: float|null, max_balance: float|null}
     */
    public function limits(WhatsappWallet $wallet): array
    {
        $cap = $this->dailySendCap($wallet);
        $wallet->resetDailyTransferIfNeeded();
        $used = (float) $wallet->daily_transfer_total;
        $maxBal = $this->maxBalanceCap($wallet);

        return [
            'currency' => 'NGN',
            'daily_send' => $cap,
            'daily_send_used' => $used,
            'daily_send_remaining' => $cap === null ? null : max(0.0, $cap - $used),
            'max_balance' => $maxBal,
        ];
    }

    public function canTransact(WhatsappWallet $wallet): bool
    {
        if (! $this->appliesTo($wallet)) {
            return true;
        }

        return $this->effectiveStatus($wallet) === WhatsappWallet::KYC_STATUS_VERIFIED
            && $this->effectiveTier($wallet) >= 1;
    }

    /**
     * @return array{ok: bool, message?: string, error_code?: string}
     */
    public function assertCanDebit(WhatsappWallet $wallet, float $amount): array
    {
        if (! $this->appliesTo($wallet)) {
            return ['ok' => true];
        }
        if (! $this->canTransact($wallet)) {
            return [
                'ok' => false,
                'message' => 'This wallet is restricted until KYC is complete.',
                'error_code' => 'account_restricted',
            ];
        }

        $cap = $this->dailySendCap($wallet);
        if ($cap === null) {
            return ['ok' => true];
        }
        $wallet->resetDailyTransferIfNeeded();
        if ((float) $wallet->daily_transfer_total + $amount > $cap + 0.0001) {
            $remaining = max(0.0, $cap - (float) $wallet->daily_transfer_total);

            return [
                'ok' => false,
                'message' => 'Daily send limit is ₦'.number_format($cap, 2).
                    ' (₦'.number_format($remaining, 2).' left today). Complete KYC upgrades for a higher limit.',
                'error_code' => 'kyc_tier_blocked',
            ];
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, message?: string, error_code?: string}
     */
    public function assertCanCredit(WhatsappWallet $wallet, float $amount): array
    {
        if (! $this->appliesTo($wallet)) {
            return ['ok' => true];
        }
        if (! $this->canTransact($wallet)) {
            return [
                'ok' => false,
                'message' => 'This wallet is restricted until KYC is complete.',
                'error_code' => 'account_restricted',
            ];
        }
        $max = $this->maxBalanceCap($wallet);
        if ($max === null) {
            return ['ok' => true];
        }
        $newBal = (float) $wallet->balance + $amount;
        if ($newBal > $max + 0.0001) {
            return [
                'ok' => false,
                'message' => 'Wallet cannot exceed ₦'.number_format($max, 2).' at this KYC tier.',
                'error_code' => 'kyc_tier_blocked',
            ];
        }

        return ['ok' => true];
    }

    public function legalNameError(string $fname, string $lname): ?string
    {
        $fname = trim($fname);
        $lname = trim($lname);
        if (mb_strlen($fname) < 2 || mb_strlen($lname) < 2) {
            return 'Enter your full legal first and last name (not initials).';
        }
        if (preg_match('/^[A-Za-z]\.?$/', $fname) || preg_match('/^[A-Za-z]\.?$/', $lname)) {
            return 'Enter your full legal name, not initials.';
        }

        return null;
    }

    /**
     * @return array{ok: bool, message: string, error_code?: string, http_status?: int}
     */
    public function assertUniqueIdentity(?WhatsappWallet $except, ?string $bvn, ?string $nin, ?string $email, ?string $phoneE164): array
    {
        if ($bvn !== null && $bvn !== '') {
            $q = WhatsappWallet::query()->where('kyc_bvn', $bvn);
            if ($except) {
                $q->where('id', '!=', $except->id);
            }
            if ($q->exists()) {
                return ['ok' => false, 'message' => 'This BVN is already registered.', 'error_code' => 'duplicate_identity', 'http_status' => 409];
            }
        }
        if ($nin !== null && $nin !== '') {
            $q = WhatsappWallet::query()->where('kyc_nin', $nin);
            if ($except) {
                $q->where('id', '!=', $except->id);
            }
            if ($q->exists()) {
                return ['ok' => false, 'message' => 'This NIN is already registered.', 'error_code' => 'duplicate_identity', 'http_status' => 409];
            }
        }
        if ($email !== null && $email !== '') {
            $q = WhatsappWallet::query()->where('kyc_email', strtolower($email));
            if ($except) {
                $q->where('id', '!=', $except->id);
            }
            if ($q->exists()) {
                return ['ok' => false, 'message' => 'This email is already registered.', 'error_code' => 'duplicate_identity', 'http_status' => 409];
            }
        }

        return ['ok' => true, 'message' => 'OK'];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{ok: bool, message: string, error_code?: string, data?: array<string, mixed>}
     */
    public function submitIdentity(WhatsappWallet $wallet, array $input, string $actorType = 'consumer', ?int $actorId = null): array
    {
        $fname = trim((string) (($input['fname'] ?? null) ?: $wallet->kyc_fname ?? ''));
        $lname = trim((string) (($input['lname'] ?? null) ?: $wallet->kyc_lname ?? ''));
        $dob = trim((string) (($input['dob'] ?? null) ?: ($wallet->kyc_dob?->format('Y-m-d') ?? '')));
        $gender = strtolower(trim((string) (($input['gender'] ?? null) ?: $wallet->kyc_gender ?? '')));
        if ($gender === 'm') {
            $gender = 'male';
        }
        if ($gender === 'f') {
            $gender = 'female';
        }
        $emailRaw = $input['email'] ?? null;
        $email = is_string($emailRaw) && trim($emailRaw) !== ''
            ? strtolower(trim($emailRaw))
            : strtolower(trim((string) ($wallet->kyc_email ?? '')));
        $bvn = preg_replace('/\D+/', '', (string) ($input['bvn'] ?? '')) ?? '';
        $nin = preg_replace('/\D+/', '', (string) ($input['nin'] ?? '')) ?? '';

        if ($err = $this->legalNameError($fname, $lname)) {
            return ['ok' => false, 'message' => $err, 'error_code' => 'kyc_incomplete'];
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
            return ['ok' => false, 'message' => 'Date of birth must be YYYY-MM-DD.', 'error_code' => 'kyc_incomplete'];
        }
        if (! in_array($gender, ['male', 'female'], true)) {
            return ['ok' => false, 'message' => 'Gender is required (male or female).', 'error_code' => 'kyc_incomplete'];
        }
        if ($emailErr = WhatsappWalletKycInputGuard::emailError($email)) {
            return ['ok' => false, 'message' => $emailErr, 'error_code' => 'kyc_incomplete'];
        }
        $useBvn = strlen($bvn) === 11;
        $useNin = strlen($nin) === 11;
        if (! $useBvn && ! $useNin) {
            return ['ok' => false, 'message' => 'BVN or NIN (11 digits) is required.', 'error_code' => 'kyc_incomplete'];
        }
        if ($useBvn && ($bvnErr = WhatsappWalletKycInputGuard::bvnOrNinError($bvn, 'BVN'))) {
            return ['ok' => false, 'message' => $bvnErr, 'error_code' => 'kyc_incomplete'];
        }
        if ($useNin && ($ninErr = WhatsappWalletKycInputGuard::bvnOrNinError($nin, 'NIN'))) {
            return ['ok' => false, 'message' => $ninErr, 'error_code' => 'kyc_incomplete'];
        }

        $dup = $this->assertUniqueIdentity(
            $wallet,
            $useBvn ? $bvn : null,
            $useNin ? $nin : null,
            $email,
            (string) $wallet->phone_e164,
        );
        if (! $dup['ok']) {
            return $dup;
        }

        $checks = [];
        if ($useBvn) {
            $checks[] = ['bvn' => $bvn, 'nin' => null];
        }
        if ($useNin) {
            $checks[] = ['bvn' => null, 'nin' => $nin];
        }

        $lastVerified = null;
        foreach ($checks as $check) {
            try {
                $verified = $this->identity->verifyPersonal(
                    $fname,
                    $lname,
                    $dob,
                    $check['bvn'],
                    $check['nin'],
                );
            } catch (\Throwable $e) {
                $wallet->kyc_status = WhatsappWallet::KYC_STATUS_REVIEW;
                $wallet->save();
                $this->audit($wallet, 'identity_provider_failed', ['error' => $e->getMessage()], $actorType, $actorId);

                return [
                    'ok' => false,
                    'message' => 'Identity verification is temporarily unavailable. Your account is in review.',
                    'error_code' => 'identity_provider_unavailable',
                    'data' => $this->payload($wallet),
                ];
            }

            if (! ($verified['ok'] ?? false)) {
                $wallet->fill([
                    'kyc_fname' => $fname,
                    'kyc_lname' => $lname,
                    'kyc_dob' => $dob,
                    'kyc_gender' => $gender,
                    'kyc_email' => $email,
                    'kyc_status' => WhatsappWallet::KYC_STATUS_REVIEW,
                ]);
                $wallet->save();
                $this->audit($wallet, 'identity_mismatch', [
                    'message' => $verified['message'] ?? '',
                    'reference' => $verified['reference'] ?? null,
                ], $actorType, $actorId);

                return [
                    'ok' => false,
                    'message' => (string) ($verified['message'] ?? 'Identity mismatch.'),
                    'error_code' => 'identity_mismatch',
                    'data' => $this->payload($wallet),
                ];
            }
            $lastVerified = $verified;
        }

        $verified = $lastVerified ?? ['full_name' => trim($fname.' '.$lname), 'dob' => $dob, 'reference' => ''];

        $legal = trim((string) ($verified['full_name'] ?? trim($fname.' '.$lname)));
        $parts = preg_split('/\s+/', $legal) ?: [];
        if (count($parts) >= 2) {
            $fname = array_shift($parts);
            $lname = implode(' ', $parts);
        }

        if ($useBvn) {
            $wallet->kyc_bvn = $bvn;
            $wallet->kyc_bvn_verified_at = now();
        }
        if ($useNin) {
            $wallet->kyc_nin = $nin;
            $wallet->kyc_nin_verified_at = now();
        }

        $wallet->kyc_fname = $fname;
        $wallet->kyc_lname = $lname;
        $wallet->kyc_dob = $dob;
        $wallet->kyc_gender = $gender;
        $wallet->kyc_email = $email;
        $wallet->kyc_mevon_full_name = $legal;
        $wallet->kyc_identity_reference = (string) ($verified['reference'] ?? '');
        $wallet->kyc_identity_snapshot = [
            'full_name' => $legal,
            'dob' => $verified['dob'] ?? $dob,
            'reference' => $verified['reference'] ?? null,
            'at' => now()->toIso8601String(),
        ];
        $wallet->kyc_verified_at = $wallet->kyc_verified_at ?? now();
        $this->applyDerivedTierAndStatus($wallet);
        $wallet->save();

        $this->audit($wallet, 'identity_verified', [
            'bvn' => $useBvn,
            'nin' => $useNin,
            'reference' => $verified['reference'] ?? null,
        ], $actorType, $actorId);

        $this->maybeQueuePersonalVa($wallet);

        return [
            'ok' => true,
            'message' => 'Identity verified.',
            'data' => $this->payload($wallet->fresh()),
        ];
    }

    /**
     * @return array{ok: bool, message: string, error_code?: string, data?: array<string, mixed>}
     */
    public function storeIdDocument(WhatsappWallet $wallet, string $idType, UploadedFile $file): array
    {
        $idType = strtolower(trim($idType));
        if (! in_array($idType, self::ID_TYPES, true)) {
            return ['ok' => false, 'message' => 'Choose a valid ID type.', 'error_code' => 'kyc_incomplete'];
        }
        if (! $this->hasVerifiedBvn($wallet) || ! $this->hasVerifiedNin($wallet)) {
            return [
                'ok' => false,
                'message' => 'Verify both BVN and NIN before uploading an ID.',
                'error_code' => 'kyc_tier_blocked',
            ];
        }

        $path = $file->store('kyc/'.$wallet->id.'/id', 'local');
        $wallet->kyc_id_document_type = $idType;
        $wallet->kyc_id_document_path = $path;
        $wallet->kyc_id_document_status = self::DOC_PENDING;
        $wallet->kyc_id_verified_at = null;
        $this->applyDerivedTierAndStatus($wallet);
        $wallet->save();
        $this->audit($wallet, 'id_document_uploaded', ['id_type' => $idType, 'path' => $path], 'consumer', $wallet->id);

        return ['ok' => true, 'message' => 'ID uploaded and pending review.', 'data' => $this->payload($wallet)];
    }

    public function acceptIdDocument(WhatsappWallet $wallet, string $actorType = 'admin', ?int $actorId = null): void
    {
        $wallet->kyc_id_document_status = self::DOC_ACCEPTED;
        $wallet->kyc_id_verified_at = now();
        $this->applyDerivedTierAndStatus($wallet);
        $wallet->save();
        $this->audit($wallet, 'id_document_accepted', [], $actorType, $actorId);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{ok: bool, message: string, error_code?: string, data?: array<string, mixed>}
     */
    public function storeAddress(WhatsappWallet $wallet, array $input, ?UploadedFile $evidence = null): array
    {
        if ($this->derivedTierIgnoringAddress($wallet) < 2) {
            return [
                'ok' => false,
                'message' => 'Complete BVN, NIN and ID review before address verification.',
                'error_code' => 'kyc_tier_blocked',
            ];
        }

        $line1 = trim((string) ($input['line1'] ?? ''));
        $city = trim((string) ($input['city'] ?? ''));
        $state = trim((string) ($input['state'] ?? ''));
        $sof = trim((string) ($input['source_of_funds'] ?? ''));
        if ($line1 === '' || $city === '' || $state === '') {
            return ['ok' => false, 'message' => 'Full residential address is required.', 'error_code' => 'kyc_incomplete'];
        }
        if ($sof === '') {
            return ['ok' => false, 'message' => 'Source of funds is required.', 'error_code' => 'kyc_incomplete'];
        }

        $wallet->kyc_residential_address = [
            'line1' => $line1,
            'line2' => trim((string) ($input['line2'] ?? '')),
            'city' => $city,
            'state' => $state,
            'postal_code' => trim((string) ($input['postal_code'] ?? '')),
            'country' => strtoupper(trim((string) ($input['country'] ?? 'NG'))) ?: 'NG',
        ];
        $wallet->kyc_occupation = trim((string) ($input['occupation'] ?? '')) ?: $wallet->kyc_occupation;
        $wallet->kyc_employer = trim((string) ($input['employer'] ?? '')) ?: $wallet->kyc_employer;
        $wallet->kyc_purpose_of_account = trim((string) ($input['purpose_of_account'] ?? '')) ?: $wallet->kyc_purpose_of_account;
        $wallet->kyc_source_of_funds = $sof;
        if (isset($input['expected_profile']) && is_array($input['expected_profile'])) {
            $wallet->kyc_expected_profile = $input['expected_profile'];
        }
        if ($evidence instanceof UploadedFile) {
            $wallet->kyc_address_evidence_path = $evidence->store('kyc/'.$wallet->id.'/address', 'local');
        }
        $wallet->kyc_address_status = self::DOC_PENDING;
        $wallet->kyc_address_verified_at = null;
        $this->applyDerivedTierAndStatus($wallet);
        $wallet->save();
        $this->audit($wallet, 'address_submitted', [], 'consumer', $wallet->id);

        return ['ok' => true, 'message' => 'Address submitted and pending review.', 'data' => $this->payload($wallet)];
    }

    public function acceptAddress(WhatsappWallet $wallet, string $actorType = 'admin', ?int $actorId = null): void
    {
        $wallet->kyc_address_status = self::DOC_ACCEPTED;
        $wallet->kyc_address_verified_at = now();
        $this->applyDerivedTierAndStatus($wallet);
        $wallet->save();
        $this->audit($wallet, 'address_accepted', [], $actorType, $actorId);
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function requestEmailOtp(WhatsappWallet $wallet): array
    {
        $email = strtolower(trim((string) $wallet->kyc_email));
        if ($emailErr = WhatsappWalletKycInputGuard::emailError($email)) {
            return ['ok' => false, 'message' => $emailErr];
        }
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $ttl = max(60, (int) config('consumer_wallet.otp_ttl_seconds', 600));
        Cache::put($this->emailOtpKey($wallet), [
            'code_hash' => hash('sha256', $code),
            'expires_at' => now()->addSeconds($ttl)->timestamp,
        ], $ttl);

        try {
            $brand = (string) config('whatsapp.bot_brand_name', 'Checkout');
            Mail::send('emails.login-otp-code', [
                'code' => $code,
                'ttlMinutes' => max(1, (int) round($ttl / 60)),
            ], function ($message) use ($email, $brand) {
                $message->to($email)->subject("Confirm your {$brand} email");
            });
        } catch (\Throwable $e) {
            Log::warning('wallet_kyc.email_otp_failed', ['wallet_id' => $wallet->id, 'error' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'Could not send email confirmation code.'];
        }

        return ['ok' => true, 'message' => 'Confirmation code sent to your email.'];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function verifyEmailOtp(WhatsappWallet $wallet, string $code): array
    {
        $payload = Cache::get($this->emailOtpKey($wallet));
        if (! is_array($payload) || (int) ($payload['expires_at'] ?? 0) < time()) {
            return ['ok' => false, 'message' => 'That code has expired. Request a new one.'];
        }
        if (! hash_equals((string) ($payload['code_hash'] ?? ''), hash('sha256', trim($code)))) {
            return ['ok' => false, 'message' => 'Invalid confirmation code.', 'error_code' => 'email_unverified'];
        }
        Cache::forget($this->emailOtpKey($wallet));
        $wallet->kyc_email_verified_at = now();
        $this->applyDerivedTierAndStatus($wallet);
        $wallet->save();
        $this->audit($wallet, 'email_verified', [], 'consumer', $wallet->id);
        $this->maybeQueuePersonalVa($wallet->fresh() ?? $wallet);

        return ['ok' => true, 'message' => 'Email verified.', 'data' => $this->payload($wallet)];
    }

    public function applyDerivedTierAndStatus(WhatsappWallet $wallet): void
    {
        $tier = $this->deriveTier($wallet);
        $wallet->kyc_tier = $tier > 0 ? $tier : null;

        if ((string) $wallet->kyc_status === WhatsappWallet::KYC_STATUS_REVIEW
            && ! $this->hasVerifiedBvn($wallet) && ! $this->hasVerifiedNin($wallet)) {
            return;
        }

        if ($tier >= 1 && $wallet->kyc_email_verified_at !== null) {
            $wallet->kyc_status = WhatsappWallet::KYC_STATUS_VERIFIED;
        } elseif ($tier >= 1 && $wallet->kyc_email_verified_at === null) {
            $wallet->kyc_status = WhatsappWallet::KYC_STATUS_INCOMPLETE;
        } elseif ((string) $wallet->kyc_status !== WhatsappWallet::KYC_STATUS_REVIEW) {
            $wallet->kyc_status = WhatsappWallet::KYC_STATUS_INCOMPLETE;
        }
    }

    /**
     * @return list<string>
     */
    public function missingRequirements(WhatsappWallet $wallet): array
    {
        $missing = [];
        if (trim((string) $wallet->kyc_fname) === '' || trim((string) $wallet->kyc_lname) === '') {
            $missing[] = 'legal_name';
        }
        if ($wallet->kyc_dob === null) {
            $missing[] = 'dob';
        }
        if (! in_array(strtolower((string) $wallet->kyc_gender), ['male', 'female'], true)) {
            $missing[] = 'gender';
        }
        if ($wallet->kyc_email_verified_at === null) {
            $missing[] = 'email_verified';
        }
        if (! $this->hasVerifiedBvn($wallet) && ! $this->hasVerifiedNin($wallet)) {
            $missing[] = 'identity';
        }
        if (! $this->hasVerifiedBvn($wallet)) {
            $missing[] = 'bvn';
        }
        if (! $this->hasVerifiedNin($wallet)) {
            $missing[] = 'nin';
        }
        if ($wallet->kyc_id_document_status !== self::DOC_ACCEPTED) {
            $missing[] = $wallet->kyc_id_document_status === self::DOC_PENDING ? 'id_document_review' : 'id_document';
        }
        if ($wallet->kyc_address_status !== self::DOC_ACCEPTED) {
            $missing[] = $wallet->kyc_address_status === self::DOC_PENDING ? 'address_review' : 'address';
        }
        if (trim((string) $wallet->kyc_source_of_funds) === '') {
            $missing[] = 'source_of_funds';
        }

        return array_values(array_unique($missing));
    }

    public function deriveTier(WhatsappWallet $wallet): int
    {
        $bvn = $this->hasVerifiedBvn($wallet);
        $nin = $this->hasVerifiedNin($wallet);
        if (! $bvn && ! $nin) {
            return 0;
        }
        $idOk = $wallet->kyc_id_document_status === self::DOC_ACCEPTED;
        $addrOk = $wallet->kyc_address_status === self::DOC_ACCEPTED
            && trim((string) $wallet->kyc_source_of_funds) !== '';
        if ($bvn && $nin && $idOk && $addrOk) {
            return 3;
        }
        if ($bvn && $nin && $idOk) {
            return 2;
        }

        return 1;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function audit(
        WhatsappWallet $wallet,
        string $action,
        array $payload = [],
        string $actorType = 'system',
        ?int $actorId = null,
        ?int $applicationId = null,
    ): void {
        try {
            KycAuditEvent::query()->create([
                'whatsapp_wallet_id' => $wallet->id,
                'business_account_application_id' => $applicationId,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'action' => $action,
                'payload' => $payload,
                'ip' => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('kyc_audit_failed', ['action' => $action, 'error' => $e->getMessage()]);
        }
    }

    private function maybeQueuePersonalVa(WhatsappWallet $wallet): void
    {
        if (! $this->canTransact($wallet->fresh() ?? $wallet)) {
            return;
        }
        if (trim((string) $wallet->mevon_virtual_account_number) !== '') {
            return;
        }
        $fresh = $wallet->fresh();
        if (! $fresh) {
            return;
        }
        try {
            $this->provision->dispatchPersonalIfReady($fresh, [
                'fname' => (string) $fresh->kyc_fname,
                'lname' => (string) $fresh->kyc_lname,
                'dob' => $fresh->kyc_dob?->format('Y-m-d'),
                'email' => (string) $fresh->kyc_email,
                'gender' => (string) $fresh->kyc_gender,
                'bvn' => $fresh->kyc_bvn,
                'nin' => $fresh->kyc_nin,
            ]);
        } catch (\Throwable $e) {
            Log::warning('wallet_kyc.va_queue_failed', [
                'wallet_id' => $fresh->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function syncDerivedState(WhatsappWallet $wallet): void
    {
        if (! $this->appliesTo($wallet) && $wallet->kyc_status === null && $wallet->kyc_tier === null) {
            return;
        }
        if ($wallet->kyc_status === null && ($this->hasVerifiedBvn($wallet) || $this->hasVerifiedNin($wallet) || $wallet->kyc_verified_at)) {
            if ($wallet->kyc_verified_at && ! $this->hasVerifiedBvn($wallet) && $this->hasBvnDigits($wallet)) {
                $wallet->kyc_bvn_verified_at = $wallet->kyc_verified_at;
            }
            if ($wallet->kyc_verified_at && ! $this->hasVerifiedNin($wallet) && $this->hasNinDigits($wallet)) {
                $wallet->kyc_nin_verified_at = $wallet->kyc_verified_at;
            }
            $this->applyDerivedTierAndStatus($wallet);
            $wallet->save();
        }
    }

    private function effectiveStatus(WhatsappWallet $wallet): string
    {
        $s = (string) ($wallet->kyc_status ?? '');
        if ($s !== '') {
            return $s;
        }

        return $this->appliesTo($wallet)
            ? WhatsappWallet::KYC_STATUS_INCOMPLETE
            : WhatsappWallet::KYC_STATUS_VERIFIED;
    }

    private function effectiveTier(WhatsappWallet $wallet): int
    {
        if ($wallet->kyc_tier !== null) {
            return (int) $wallet->kyc_tier;
        }

        return $this->deriveTier($wallet);
    }

    private function dailySendCap(WhatsappWallet $wallet): ?float
    {
        if (! $this->appliesTo($wallet)) {
            return $wallet->isTier1() ? $wallet->tier1DailyOutLimit() : null;
        }
        if (! $this->canTransact($wallet)) {
            return 0.0;
        }

        return match ($this->effectiveTier($wallet)) {
            3 => (float) config('consumer_wallet.kyc_tier3_daily_limit', 1_000_000),
            2 => (float) config('consumer_wallet.kyc_tier2_daily_limit', 200_000),
            default => (float) config('consumer_wallet.kyc_tier1_daily_limit', 50_000),
        };
    }

    private function maxBalanceCap(WhatsappWallet $wallet): ?float
    {
        if (! $this->appliesTo($wallet)) {
            return $wallet->isTier1() ? $wallet->tier1MaxBalance() : null;
        }
        if (! $this->canTransact($wallet)) {
            return 0.0;
        }

        return match ($this->effectiveTier($wallet)) {
            3 => (float) config('consumer_wallet.kyc_tier3_max_balance', 1_000_000),
            2 => (float) config('consumer_wallet.kyc_tier2_max_balance', 200_000),
            default => (float) config('consumer_wallet.kyc_tier1_max_balance', 50_000),
        };
    }

    private function derivedTierIgnoringAddress(WhatsappWallet $wallet): int
    {
        $bvn = $this->hasVerifiedBvn($wallet);
        $nin = $this->hasVerifiedNin($wallet);
        $idOk = $wallet->kyc_id_document_status === self::DOC_ACCEPTED;
        if ($bvn && $nin && $idOk) {
            return 2;
        }
        if ($bvn || $nin) {
            return 1;
        }

        return 0;
    }

    private function hasBvnDigits(WhatsappWallet $wallet): bool
    {
        return strlen(preg_replace('/\D+/', '', (string) $wallet->kyc_bvn) ?? '') === 11;
    }

    private function hasNinDigits(WhatsappWallet $wallet): bool
    {
        return strlen(preg_replace('/\D+/', '', (string) $wallet->kyc_nin) ?? '') === 11;
    }

    private function hasVerifiedBvn(WhatsappWallet $wallet): bool
    {
        return $this->hasBvnDigits($wallet) && $wallet->kyc_bvn_verified_at !== null;
    }

    private function hasVerifiedNin(WhatsappWallet $wallet): bool
    {
        return $this->hasNinDigits($wallet) && $wallet->kyc_nin_verified_at !== null;
    }

    private function statusErrorCode(string $status): ?string
    {
        return match ($status) {
            WhatsappWallet::KYC_STATUS_RESTRICTED, WhatsappWallet::KYC_STATUS_REVIEW, WhatsappWallet::KYC_STATUS_INCOMPLETE => 'account_restricted',
            default => null,
        };
    }

    private function emailOtpKey(WhatsappWallet $wallet): string
    {
        return 'consumer_kyc_email_otp:'.$wallet->id;
    }
}
