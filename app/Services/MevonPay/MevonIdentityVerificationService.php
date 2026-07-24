<?php

namespace App\Services\MevonPay;

use App\Services\Whatsapp\WhatsappWalletNameMatcher;

/**
 * Run Mevon /V1/bvn-verify or /V1/nin-verify before permanent account creation.
 * Platform covers verification fees (₦30 BVN / ₦50 NIN per success).
 */
class MevonIdentityVerificationService
{
    public function __construct(
        private MevonBvnVerifyService $bvnVerify,
        private MevonNinVerifyService $ninVerify,
    ) {}

    public function isBvnConfigured(): bool
    {
        return $this->bvnVerify->isConfigured();
    }

    public function isNinConfigured(): bool
    {
        return $this->ninVerify->isConfigured();
    }

    /**
     * @return array{ok: bool, message: string, full_name?: string, reference?: string, raw?: array<string, mixed>}
     */
    public function verifyPersonal(
        string $firstName,
        string $lastName,
        string $dobYmd,
        ?string $bvn11 = null,
        ?string $nin11 = null,
        ?string $submittedFullName = null,
    ): array {
        $firstName = trim($firstName);
        $lastName = trim($lastName);
        if ($firstName === '' || $lastName === '') {
            return ['ok' => false, 'message' => 'First and last name are required for identity verification.'];
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dobYmd)) {
            return ['ok' => false, 'message' => 'Valid date of birth (YYYY-MM-DD) is required.'];
        }

        $bvn = $bvn11 !== null ? (preg_replace('/\D+/', '', $bvn11) ?? '') : '';
        $nin = $nin11 !== null ? (preg_replace('/\D+/', '', $nin11) ?? '') : '';
        $useBvn = strlen($bvn) === 11;
        $useNin = ! $useBvn && strlen($nin) === 11;

        if (! $useBvn && ! $useNin) {
            return ['ok' => false, 'message' => 'BVN or NIN (11 digits) is required for verification.'];
        }

        try {
            $result = $useBvn
                ? $this->bvnVerify->verify($bvn, $dobYmd, $firstName, $lastName)
                : $this->ninVerify->verify($nin, $dobYmd, $firstName, $lastName);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $providerName = trim((string) ($result['full_name'] ?? ''));
        $expectedName = trim((string) ($submittedFullName ?? trim($firstName.' '.$lastName)));
        if ($providerName === '' || ! WhatsappWalletNameMatcher::passes($expectedName, $providerName)) {
            $message = $providerName === ''
                ? 'Mevon returned no registered name for this identity number.'
                : 'Name mismatch: submitted "'.$expectedName.'" does not match Mevon record "'.$providerName.'".';

            return [
                'ok' => false,
                'message' => $message,
                'full_name' => $providerName !== '' ? $providerName : null,
                'reference' => (string) ($result['reference'] ?? ''),
                'raw' => $result['raw'] ?? [],
            ];
        }

        return [
            'ok' => true,
            'message' => 'Identity verified via Mevon.',
            'full_name' => $providerName,
            'reference' => (string) ($result['reference'] ?? ''),
            'raw' => $result['raw'] ?? [],
        ];
    }
}
