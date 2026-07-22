<?php

namespace App\Services\Admin;

use App\Models\Admin;
use App\Models\Business;
use App\Models\BusinessVerification;
use App\Services\MevonPay\MevonBvnVerifyService;
use App\Services\MevonPay\MevonNinVerifyService;
use App\Services\Whatsapp\WhatsappWalletNameMatcher;

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
     * @return array{ok: bool, message: string, verification?: BusinessVerification}
     */
    public function verify(BusinessVerification $verification, Admin $admin, ?string $dobOverride = null): array
    {
        if (! $verification->requiresMevonVerification()) {
            return ['ok' => false, 'message' => 'This verification type does not require Mevon identity check.'];
        }

        $business = $verification->business;
        if ($business === null) {
            return ['ok' => false, 'message' => 'Business not found for this verification.'];
        }

        $identityNumber = $verification->extractSubmittedIdentityNumber();
        if ($identityNumber === null) {
            return ['ok' => false, 'message' => 'Could not read the submitted BVN/NIN number.'];
        }

        $nameParts = $this->splitName((string) $business->name);
        if ($nameParts['first'] === '' || $nameParts['last'] === '') {
            return ['ok' => false, 'message' => 'Business legal name is required to verify identity.'];
        }

        $dob = $this->resolveDob($business, $dobOverride);
        if ($dob === null) {
            return ['ok' => false, 'message' => 'Date of birth is required. Enter signatory DOB on the verify form or ensure the business profile has it.'];
        }

        try {
            $providerResult = match ($verification->verification_type) {
                BusinessVerification::TYPE_BVN => $this->verifyBvn($identityNumber, $dob, $nameParts['first'], $nameParts['last']),
                BusinessVerification::TYPE_NIN => $this->verifyNin($identityNumber, $dob, $nameParts['first'], $nameParts['last']),
                default => throw new \RuntimeException('Unsupported verification type.'),
            };
        } catch (\Throwable $e) {
            $verification->update([
                'provider_verified_at' => now(),
                'provider_verified_by' => $admin->id,
                'provider_verified_name' => null,
                'provider_verify_reference' => null,
                'provider_verify_status' => self::STATUS_FAILED,
                'provider_verify_message' => $e->getMessage(),
                'provider_verify_payload' => null,
            ]);

            return ['ok' => false, 'message' => $e->getMessage(), 'verification' => $verification->fresh()];
        }

        $providerName = trim((string) ($providerResult['full_name'] ?? ''));
        $submittedName = trim((string) $business->name);
        $nameMatches = $providerName !== '' && WhatsappWalletNameMatcher::passes($submittedName, $providerName);

        if (! $nameMatches) {
            $message = $providerName === ''
                ? 'Mevon returned no registered name for this identity number.'
                : 'Name mismatch: submitted "'.$submittedName.'" does not match Mevon record "'.$providerName.'".';

            $verification->update([
                'provider_verified_at' => now(),
                'provider_verified_by' => $admin->id,
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
            'provider_verified_by' => $admin->id,
            'provider_verified_name' => $providerName,
            'provider_verify_reference' => (string) ($providerResult['reference'] ?? ''),
            'provider_verify_status' => self::STATUS_PASSED,
            'provider_verify_message' => 'Mevon verified identity and name match.',
            'provider_verify_payload' => $providerResult['raw'] ?? null,
        ]);

        return [
            'ok' => true,
            'message' => 'Identity verified via Mevon. Registered name: '.$providerName,
            'verification' => $verification->fresh(),
        ];
    }

    /**
     * @return array{reference: string, full_name: string, raw: array<string, mixed>}
     */
    private function verifyBvn(string $bvn, string $dob, string $firstName, string $lastName): array
    {
        if (! $this->bvnVerify->isConfigured()) {
            throw new \RuntimeException('Mevon BVN verification is not configured.');
        }

        return $this->bvnVerify->verify($bvn, $dob, $firstName, $lastName);
    }

    /**
     * @return array{reference: string, full_name: string, raw: array<string, mixed>}
     */
    private function verifyNin(string $nin, string $dob, string $firstName, string $lastName): array
    {
        if (! $this->ninVerify->isConfigured()) {
            throw new \RuntimeException('Mevon NIN verification is not configured.');
        }

        return $this->ninVerify->verify($nin, $dob, $firstName, $lastName);
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
