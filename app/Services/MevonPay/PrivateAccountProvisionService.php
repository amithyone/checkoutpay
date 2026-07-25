<?php

namespace App\Services\MevonPay;

use App\Jobs\CreateBusinessPrivateAccountJob;
use App\Jobs\CreatePersonalPrivateAccountJob;
use App\Models\Business;
use App\Models\BusinessVerification;
use App\Models\WhatsappWallet;
use App\Services\Admin\BusinessKycMevonVerificationService;
use Illuminate\Support\Facades\Log;

/**
 * Gate checks and queue dispatch for Mevon /V1/pivateaccount provisioning.
 */
final class PrivateAccountProvisionService
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public function __construct(
        private MevonPrivateAccountService $privateAccount,
        private BusinessKycMevonVerificationService $businessIdentityVerify,
    ) {}

    public function isConfigured(): bool
    {
        return $this->privateAccount->isConfigured();
    }

    /**
     * @return array{ready: bool, missing: list<string>}
     */
    public function businessReadiness(Business $business): array
    {
        $business->refresh();
        $missing = [];

        if (! empty($business->rubies_business_account_number)) {
            return ['ready' => false, 'missing' => ['Pay-in account already exists.']];
        }

        if (! $this->privateAccount->isConfigured()) {
            return ['ready' => false, 'missing' => ['Mevon private account API is not configured.']];
        }

        if ($this->verifiedBusinessIdentity($business) === null) {
            $missing[] = 'BVN or NIN must pass Mevon identity verification before a pay-in account can be created.';
        }

        if (! $business->hasAllKycDocumentsApproved()) {
            $missing[] = 'All required KYC documents must be approved.';
        }

        if (trim((string) $business->name) === '') {
            $missing[] = 'Business legal name is required.';
        }

        if (trim((string) $business->phone) === '') {
            $missing[] = 'Business phone is required.';
        }

        $email = strtolower(trim((string) $business->email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $missing[] = 'Valid business email is required.';
        }

        if ($business->rubies_signatory_dob === null) {
            $missing[] = 'Signatory date of birth is required.';
        }

        if (trim((string) $business->cac_registration_number) === '') {
            $missing[] = 'CAC / RC registration number is required.';
        }

        $status = (string) ($business->rubies_account_provision_status ?? '');
        if (in_array($status, [self::STATUS_QUEUED, self::STATUS_PROCESSING], true)) {
            return ['ready' => false, 'missing' => ['Pay-in account creation is already in progress.']];
        }

        return ['ready' => $missing === [], 'missing' => $missing];
    }

    /**
     * @return array{dispatched: bool, message: string, missing?: list<string>}
     */
    public function dispatchBusinessIfReady(Business $business, bool $forceRetry = false): array
    {
        if ($this->businessIdentityVerify->isAvailable()) {
            $this->businessIdentityVerify->verifyPendingIdentityRowsForBusiness($business);
            $business->refresh();
        }

        $readiness = $this->businessReadiness($business);
        if (! $readiness['ready']) {
            if ($forceRetry && $business->rubies_account_provision_status === self::STATUS_FAILED) {
                $blocking = array_filter(
                    $readiness['missing'],
                    fn (string $item) => $item !== 'Pay-in account creation is already in progress.'
                );
                if ($blocking !== []) {
                    return [
                        'dispatched' => false,
                        'message' => implode(' ', $blocking),
                        'missing' => $blocking,
                    ];
                }
            } else {
                return [
                    'dispatched' => false,
                    'message' => implode(' ', $readiness['missing']),
                    'missing' => $readiness['missing'],
                ];
            }
        }

        $business->update([
            'rubies_account_provision_status' => self::STATUS_QUEUED,
            'rubies_account_provision_error' => null,
            'rubies_account_provision_queued_at' => now(),
        ]);

        CreateBusinessPrivateAccountJob::dispatch($business->id);

        Log::info('private_account.business_queued', ['business_id' => $business->id]);

        return [
            'dispatched' => true,
            'message' => 'Pay-in account creation has been queued. Refresh shortly for account details.',
        ];
    }

    /**
     * @return array{bvn: ?string, nin: ?string}
     */
    public function verifiedBusinessIdentity(Business $business): ?array
    {
        foreach ([BusinessVerification::TYPE_BVN, BusinessVerification::TYPE_NIN] as $type) {
            $row = $business->verifications()
                ->where('verification_type', $type)
                ->where('provider_verify_status', BusinessVerification::PROVIDER_VERIFY_PASSED)
                ->orderByDesc('provider_verified_at')
                ->first();

            if ($row === null) {
                continue;
            }

            $number = $row->extractSubmittedIdentityNumber();
            if ($number === null || strlen($number) !== 11) {
                continue;
            }

            return $type === BusinessVerification::TYPE_BVN
                ? ['bvn' => $number, 'nin' => null]
                : ['bvn' => null, 'nin' => $number];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{ready: bool, missing: list<string>}
     */
    public function personalReadiness(WhatsappWallet $wallet, array $input = []): array
    {
        $wallet->refresh();
        $missing = [];

        if (trim((string) $wallet->mevon_virtual_account_number) !== '') {
            return ['ready' => false, 'missing' => ['Permanent account already exists.']];
        }

        if (! $this->privateAccount->isConfigured()) {
            return ['ready' => false, 'missing' => ['Tier 2 provisioning is not configured.']];
        }

        $fname = trim((string) ($input['fname'] ?? $wallet->kyc_fname ?? ''));
        $lname = trim((string) ($input['lname'] ?? $wallet->kyc_lname ?? ''));
        $dob = (string) ($input['dob'] ?? optional($wallet->kyc_dob)?->format('Y-m-d') ?? '');
        $email = strtolower(trim((string) ($input['email'] ?? $wallet->kyc_email ?? '')));
        $bvn = preg_replace('/\D+/', '', (string) ($input['bvn'] ?? $wallet->kyc_bvn ?? '')) ?? '';
        $nin = preg_replace('/\D+/', '', (string) ($input['nin'] ?? $wallet->kyc_nin ?? '')) ?? '';
        $gender = strtolower(trim((string) ($input['gender'] ?? $wallet->kyc_gender ?? '')));

        if ($fname === '' || $lname === '') {
            $missing[] = 'First and last name are required.';
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
            $missing[] = 'Valid date of birth (YYYY-MM-DD) is required.';
        }

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $missing[] = 'Valid email is required.';
        }

        if (! in_array($gender, ['male', 'female'], true)) {
            $missing[] = 'Gender is required (male or female).';
        }

        $useBvn = strlen($bvn) === 11;
        $useNin = ! $useBvn && strlen($nin) === 11;
        if (! $useBvn && ! $useNin) {
            $missing[] = 'BVN or NIN (11 digits) is required.';
        }

        $status = (string) ($wallet->private_account_provision_status ?? '');
        if (in_array($status, [self::STATUS_QUEUED, self::STATUS_PROCESSING], true)) {
            return ['ready' => false, 'missing' => ['Account creation is already in progress.']];
        }

        return ['ready' => $missing === [], 'missing' => $missing];
    }

    /**
     * @param  array<string, mixed>  $input  fname, lname, dob, email, gender, bvn|nin
     * @return array{dispatched: bool, message: string, missing?: list<string>}
     */
    public function dispatchPersonalIfReady(WhatsappWallet $wallet, array $input, bool $forceRetry = false): array
    {
        $readiness = $this->personalReadiness($wallet, $input);
        if (! $readiness['ready']) {
            if ($forceRetry && $wallet->private_account_provision_status === self::STATUS_FAILED) {
                $blocking = array_filter(
                    $readiness['missing'],
                    fn (string $item) => $item !== 'Account creation is already in progress.'
                );
                if ($blocking !== []) {
                    return [
                        'dispatched' => false,
                        'message' => implode(' ', $blocking),
                        'missing' => $blocking,
                    ];
                }
            } else {
                return [
                    'dispatched' => false,
                    'message' => implode(' ', $readiness['missing']),
                    'missing' => $readiness['missing'],
                ];
            }
        }

        $bvn = preg_replace('/\D+/', '', (string) ($input['bvn'] ?? $wallet->kyc_bvn ?? '')) ?? '';
        $nin = preg_replace('/\D+/', '', (string) ($input['nin'] ?? $wallet->kyc_nin ?? '')) ?? '';
        $useBvn = strlen($bvn) === 11;
        $useNin = ! $useBvn && strlen($nin) === 11;

        $fname = trim((string) ($input['fname'] ?? $wallet->kyc_fname ?? ''));
        $lname = trim((string) ($input['lname'] ?? $wallet->kyc_lname ?? ''));
        $gender = strtolower(trim((string) ($input['gender'] ?? $wallet->kyc_gender ?? '')));
        $dob = (string) ($input['dob'] ?? optional($wallet->kyc_dob)?->format('Y-m-d') ?? '');
        $email = strtolower(trim((string) ($input['email'] ?? $wallet->kyc_email ?? '')));

        $wallet->update([
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'kyc_fname' => $fname !== '' ? $fname : null,
            'kyc_lname' => $lname !== '' ? $lname : null,
            'kyc_gender' => in_array($gender, ['male', 'female'], true) ? $gender : null,
            'kyc_dob' => $dob !== '' ? $dob : null,
            'kyc_bvn' => $useBvn ? $bvn : null,
            'kyc_nin' => $useNin ? $nin : null,
            'kyc_email' => $email !== '' ? $email : null,
            'kyc_verified_at' => now(),
            'rubies_account_type' => 'personal',
            'private_account_provision_status' => self::STATUS_QUEUED,
            'private_account_provision_error' => null,
            'private_account_provision_queued_at' => now(),
        ]);

        CreatePersonalPrivateAccountJob::dispatch($wallet->id);

        Log::info('private_account.personal_queued', ['wallet_id' => $wallet->id]);

        return [
            'dispatched' => true,
            'message' => 'Account is being created. Check back shortly.',
        ];
    }

    /**
     * Queue Mevon /V1/pivateaccount using KYC already stored on the wallet (admin retry / manual push).
     *
     * @return array{dispatched: bool, message: string, missing?: list<string>}
     */
    public function dispatchPersonalFromStoredKyc(WhatsappWallet $wallet, bool $forceRetry = false): array
    {
        $wallet->refresh();

        if (($wallet->rubies_account_type ?? 'personal') === 'business') {
            $bvn = preg_replace('/\D+/', '', (string) $wallet->kyc_bvn) ?? '';

            return $this->dispatchPersonalBusinessIfReady($wallet, [
                'cac' => (string) ($wallet->kyc_cac ?? ''),
                'dob' => optional($wallet->kyc_dob)?->format('Y-m-d') ?? '',
                'email' => (string) ($wallet->kyc_email ?? ''),
                'bvn' => $bvn,
                'fname' => (string) ($wallet->kyc_fname ?? ''),
                'lname' => (string) ($wallet->kyc_lname ?? ''),
                'business_name' => trim((string) ($wallet->kyc_cac ?? '')) !== ''
                    ? (string) $wallet->kyc_cac
                    : trim((string) ($wallet->kyc_fname ?? '').' '.(string) ($wallet->kyc_lname ?? '')),
            ], $forceRetry);
        }

        $bvn = preg_replace('/\D+/', '', (string) $wallet->kyc_bvn) ?? '';
        $nin = preg_replace('/\D+/', '', (string) $wallet->kyc_nin) ?? '';
        $useBvn = strlen($bvn) === 11;

        return $this->dispatchPersonalIfReady($wallet, [
            'fname' => (string) ($wallet->kyc_fname ?? ''),
            'lname' => (string) ($wallet->kyc_lname ?? ''),
            'dob' => optional($wallet->kyc_dob)?->format('Y-m-d') ?? '',
            'email' => (string) ($wallet->kyc_email ?? ''),
            'gender' => (string) ($wallet->kyc_gender ?? ''),
            'bvn' => $useBvn ? $bvn : null,
            'nin' => ! $useBvn && strlen($nin) === 11 ? $nin : null,
        ], $forceRetry);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{dispatched: bool, message: string, missing?: list<string>}
     */
    public function dispatchPersonalBusinessIfReady(WhatsappWallet $wallet, array $input, bool $forceRetry = false): array
    {
        $wallet->refresh();

        if (trim((string) $wallet->mevon_virtual_account_number) !== '') {
            return ['dispatched' => false, 'message' => 'Permanent account already exists.'];
        }

        if (! $this->privateAccount->isConfigured()) {
            return ['dispatched' => false, 'message' => 'Tier 2 provisioning is not configured.'];
        }

        $cac = strtoupper(trim((string) ($input['cac'] ?? '')));
        $dob = (string) ($input['dob'] ?? '');
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $bvn = preg_replace('/\D+/', '', (string) ($input['bvn'] ?? '')) ?? '';
        $fname = trim((string) ($input['fname'] ?? ''));
        $lname = trim((string) ($input['lname'] ?? ''));
        $businessName = trim((string) ($input['business_name'] ?? $cac));

        if ($fname === '' && trim((string) $wallet->kyc_fname) !== '') {
            $fname = trim((string) $wallet->kyc_fname);
        }
        if ($lname === '' && trim((string) $wallet->kyc_lname) !== '') {
            $lname = trim((string) $wallet->kyc_lname);
        }
        if ($bvn === '' && strlen(preg_replace('/\D+/', '', (string) $wallet->kyc_bvn) ?? '') === 11) {
            $bvn = preg_replace('/\D+/', '', (string) $wallet->kyc_bvn) ?? '';
        }

        $missing = [];
        if ($cac === '' || strlen($cac) < 3) {
            $missing[] = 'CAC / registration number is required.';
        }
        if ($fname === '' || $lname === '') {
            $missing[] = 'Signatory first and last name are required.';
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
            $missing[] = 'Valid date of birth is required.';
        }
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $missing[] = 'Valid email is required.';
        }
        if (strlen($bvn) !== 11) {
            $missing[] = 'Signatory BVN (11 digits) is required.';
        }
        if ($businessName === '') {
            $missing[] = 'Business name is required.';
        }

        $status = (string) ($wallet->private_account_provision_status ?? '');
        if (in_array($status, [self::STATUS_QUEUED, self::STATUS_PROCESSING], true)) {
            $missing[] = 'Account creation is already in progress.';
        }

        if ($missing !== [] && ! ($forceRetry && $wallet->private_account_provision_status === self::STATUS_FAILED)) {
            return ['dispatched' => false, 'message' => implode(' ', $missing), 'missing' => $missing];
        }

        $wallet->update([
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'rubies_account_type' => 'business',
            'kyc_cac' => $cac,
            'kyc_fname' => $fname !== '' ? $fname : null,
            'kyc_lname' => $lname !== '' ? $lname : null,
            'kyc_gender' => null,
            'kyc_dob' => $dob,
            'kyc_bvn' => $bvn,
            'kyc_nin' => null,
            'kyc_email' => $email,
            'kyc_verified_at' => now(),
            'private_account_provision_status' => self::STATUS_QUEUED,
            'private_account_provision_error' => null,
            'private_account_provision_queued_at' => now(),
        ]);

        CreatePersonalPrivateAccountJob::dispatch($wallet->id, [
            'account_kind' => 'business',
            'business_name' => $businessName,
            'cac' => $cac,
        ]);

        return [
            'dispatched' => true,
            'message' => 'Account is being created. Check back shortly.',
        ];
    }
}
