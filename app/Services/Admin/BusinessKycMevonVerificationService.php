<?php

namespace App\Services\Admin;

use App\Models\Admin;
use App\Models\Business;
use App\Models\BusinessVerification;
use App\Services\MevonPay\MevonBvnVerifyService;
use App\Services\MevonPay\MevonNinVerifyService;
use App\Services\Whatsapp\WhatsappWalletNameMatcher;
use Illuminate\Support\Facades\Log;

/**
 * Business BVN/NIN verification via Mevon /V1/bvn-verify and /V1/nin-verify.
 * Runs automatically on merchant submission when configured; admin can re-run manually.
 * Separate from permanent account creation (/V1/pivateaccount).
 */
class BusinessKycMevonVerificationService
{
    public const STATUS_PASSED = BusinessVerification::PROVIDER_VERIFY_PASSED;

    public const STATUS_FAILED = BusinessVerification::PROVIDER_VERIFY_FAILED;

    public function __construct(
        private MevonBvnVerifyService $bvnVerify,
        private MevonNinVerifyService $ninVerify,
    ) {}

    public function isAvailable(): bool
    {
        return $this->bvnVerify->isConfigured() || $this->ninVerify->isConfigured();
    }

    /**
     * Auto-run Mevon verify when merchant submits BVN/NIN (platform covers fee).
     *
     * @return array{ok: bool, message: string, verification?: BusinessVerification, skipped?: bool}
     */
    public function verifyAutomatically(BusinessVerification $verification): array
    {
        if ($verification->isProviderVerified()) {
            return ['ok' => true, 'message' => 'Already verified.', 'verification' => $verification, 'skipped' => true];
        }

        return $this->runVerification($verification, null, null);
    }

    /**
     * @return array{ok: bool, message: string, verification?: BusinessVerification}
     */
    public function verify(BusinessVerification $verification, Admin $admin, ?string $dobOverride = null): array
    {
        return $this->runVerification($verification, $admin->id, $dobOverride);
    }

    /**
     * Verify all submitted BVN/NIN rows for a business that are not yet provider-verified.
     *
     * @return array{attempted: int, passed: int, failed: int}
     */
    public function verifyPendingIdentityRowsForBusiness(Business $business): array
    {
        $stats = ['attempted' => 0, 'passed' => 0, 'failed' => 0];

        if (! $this->isAvailable()) {
            return $stats;
        }

        $business->refresh();
        foreach ([BusinessVerification::TYPE_BVN, BusinessVerification::TYPE_NIN] as $type) {
            $row = $business->verifications()
                ->where('verification_type', $type)
                ->whereNotIn('status', [BusinessVerification::STATUS_REJECTED])
                ->orderByDesc('created_at')
                ->first();

            if ($row === null || $row->isProviderVerified()) {
                continue;
            }

            $stats['attempted']++;
            $result = $this->verifyAutomatically($row);
            if ($result['ok'] && empty($result['skipped'])) {
                $stats['passed']++;
            } elseif (! ($result['skipped'] ?? false)) {
                $stats['failed']++;
            }
        }

        if ($stats['attempted'] > 0) {
            Log::info('business.kyc.auto_identity_verify', [
                'business_id' => $business->id,
                'attempted' => $stats['attempted'],
                'passed' => $stats['passed'],
                'failed' => $stats['failed'],
            ]);
        }

        return $stats;
    }

    /**
     * @return array{ok: bool, message: string, verification?: BusinessVerification, skipped?: bool}
     */
    private function runVerification(BusinessVerification $verification, ?int $adminId, ?string $dobOverride): array
    {
        if (! $verification->requiresMevonVerification()) {
            return ['ok' => false, 'message' => 'This verification type does not require Mevon identity check.'];
        }

        $business = $verification->business;
        if ($business === null) {
            return ['ok' => false, 'message' => 'Business not found for this verification.'];
        }

        $identityNumber = $verification->extractSubmittedIdentityNumber();
        if ($identityNumber === null || strlen($identityNumber) !== 11) {
            return ['ok' => false, 'message' => 'Could not read a valid 11-digit BVN/NIN from the submission.'];
        }

        $identityName = $business->signatoryNameForIdentityVerify();
        $nameParts = $this->splitName($identityName);
        if ($nameParts['first'] === '' || $nameParts['last'] === '') {
            return ['ok' => false, 'message' => 'Signatory legal name (as on BVN/NIN) is required to verify identity.'];
        }

        $dob = $this->resolveDob($business, $dobOverride);
        if ($dob === null) {
            return ['ok' => false, 'message' => 'Date of birth is required before Mevon verification can run.'];
        }

        if ($verification->verification_type === BusinessVerification::TYPE_BVN && ! $this->bvnVerify->isConfigured()) {
            return ['ok' => false, 'message' => 'Mevon BVN verification is not configured.'];
        }
        if ($verification->verification_type === BusinessVerification::TYPE_NIN && ! $this->ninVerify->isConfigured()) {
            return ['ok' => false, 'message' => 'Mevon NIN verification is not configured.'];
        }

        try {
            $providerResult = match ($verification->verification_type) {
                BusinessVerification::TYPE_BVN => $this->bvnVerify->verify($identityNumber, $dob, $nameParts['first'], $nameParts['last']),
                BusinessVerification::TYPE_NIN => $this->ninVerify->verify($identityNumber, $dob, $nameParts['first'], $nameParts['last']),
                default => throw new \RuntimeException('Unsupported verification type.'),
            };
        } catch (\Throwable $e) {
            $verification->update([
                'provider_verified_at' => now(),
                'provider_verified_by' => $adminId,
                'provider_verified_name' => null,
                'provider_verify_reference' => null,
                'provider_verify_status' => self::STATUS_FAILED,
                'provider_verify_message' => $e->getMessage(),
                'provider_verify_payload' => null,
            ]);

            return ['ok' => false, 'message' => $e->getMessage(), 'verification' => $verification->fresh()];
        }

        $providerName = trim((string) ($providerResult['full_name'] ?? ''));
        $submittedName = $business->signatoryNameForIdentityVerify();
        $nameMatches = $providerName !== '' && WhatsappWalletNameMatcher::passes($submittedName, $providerName);

        if (! $nameMatches) {
            $message = $providerName === ''
                ? 'Mevon returned no registered name for this identity number.'
                : 'Name mismatch: submitted "'.$submittedName.'" does not match Mevon record "'.$providerName.'".';

            $verification->update([
                'provider_verified_at' => now(),
                'provider_verified_by' => $adminId,
                'provider_verified_name' => $providerName !== '' ? $providerName : null,
                'provider_verify_reference' => (string) ($providerResult['reference'] ?? ''),
                'provider_verify_status' => self::STATUS_FAILED,
                'provider_verify_message' => $message,
                'provider_verify_payload' => $providerResult['raw'] ?? null,
            ]);

            return ['ok' => false, 'message' => $message, 'verification' => $verification->fresh()];
        }

        $verification->update([
            'provider_verified_at' => now(),
            'provider_verified_by' => $adminId,
            'provider_verified_name' => $providerName,
            'provider_verify_reference' => (string) ($providerResult['reference'] ?? ''),
            'provider_verify_status' => self::STATUS_PASSED,
            'provider_verify_message' => $adminId === null
                ? 'Mevon verified identity automatically.'
                : 'Mevon verified identity and name match.',
            'provider_verify_payload' => $providerResult['raw'] ?? null,
        ]);

        return [
            'ok' => true,
            'message' => 'Identity verified via Mevon. Registered name: '.$providerName,
            'verification' => $verification->fresh(),
        ];
    }

    private function resolveDob(Business $business, ?string $override): ?string
    {
        $override = trim((string) ($override ?? ''));
        if ($override !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $override)) {
            return $override;
        }

        $dob = $business->rubies_signatory_dob;
        if ($dob !== null) {
            return $dob->format('Y-m-d');
        }

        return null;
    }

    /**
     * @return array{first: string, last: string}
     */
    private function splitName(string $fullName): array
    {
        $fullName = trim(preg_replace('/\s+/u', ' ', $fullName) ?? $fullName);
        if ($fullName === '') {
            return ['first' => '', 'last' => ''];
        }

        $parts = explode(' ', $fullName, 2);

        return [
            'first' => $parts[0],
            'last' => $parts[1] ?? $parts[0],
        ];
    }
}
