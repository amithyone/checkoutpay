<?php

namespace App\Services\Consumer;

use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Models\WhatsappWalletTransferBeneficiary;
use App\Services\Checkface\CheckfaceClient;
use App\Services\Checkface\CheckfaceProvisionService;
use App\Services\Whatsapp\PhoneNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Wallet face enrollment + challenge tokens + ≥₦30k unfamiliar-recipient gate.
 */
final class WalletFaceCheckService
{
    private const CACHE_PREFIX = 'wallet_face_challenge:';

    public function __construct(
        private CheckfaceClient $client,
    ) {}

    public function isEnabled(): bool
    {
        if ($this->client->isConfigured()) {
            return true;
        }

        if (! (bool) config('checkface.enabled', true)) {
            return false;
        }

        if (! (bool) config('checkface.auto_provision', true)) {
            return false;
        }

        return app(CheckfaceProvisionService::class)->ensureConfigured()
            && $this->client->isConfigured();
    }

    public function amountThreshold(): float
    {
        return (float) config('checkface.amount_threshold_ngn', 30000);
    }

    public function checkfaceUserId(WhatsappWallet $wallet): string
    {
        return 'w'.(int) $wallet->id;
    }

    /**
     * @return array{enabled: bool, enrolled: bool, gallery_size: int|null, threshold_ngn: float}
     */
    public function status(WhatsappWallet $wallet): array
    {
        $enrolled = $wallet->face_enrolled_at !== null;
        $gallery = null;

        if ($this->isEnabled()) {
            $remote = $this->client->learningStatus($this->checkfaceUserId($wallet));
            if ($remote['ok']) {
                $stored = (bool) ($remote['data']['profile_stored'] ?? false);
                $gallery = isset($remote['data']['gallery_size']) ? (int) $remote['data']['gallery_size'] : null;
                $enrolled = $stored || ($gallery !== null && $gallery > 0);
                if ($enrolled && $wallet->face_enrolled_at === null) {
                    $wallet->forceFill(['face_enrolled_at' => now()])->saveQuietly();
                } elseif (! $enrolled && $wallet->face_enrolled_at !== null) {
                    $wallet->forceFill(['face_enrolled_at' => null])->saveQuietly();
                }
            }
        }

        return [
            'enabled' => $this->isEnabled(),
            'enrolled' => $enrolled,
            'gallery_size' => $gallery,
            'threshold_ngn' => $this->amountThreshold(),
        ];
    }

    /**
     * @param  list<UploadedFile>  $extraSamples
     * @return array{ok: bool, message: string, data?: array<string, mixed>, http?: int}
     */
    public function enroll(WhatsappWallet $wallet, UploadedFile $photo, array $extraSamples = []): array
    {
        if (! $this->isEnabled()) {
            return ['ok' => false, 'message' => 'Face check is not available.', 'http' => 503];
        }

        $result = $this->client->enrollFace($this->checkfaceUserId($wallet), $photo, $extraSamples);
        if (! $result['ok'] || empty($result['data']['enrolled'])) {
            return [
                'ok' => false,
                'message' => $result['message'] ?: 'Could not enroll face.',
                'http' => $result['status'] >= 400 ? $result['status'] : 422,
                'data' => $result['data'],
            ];
        }

        $wallet->forceFill(['face_enrolled_at' => now()])->save();

        return [
            'ok' => true,
            'message' => 'Face enrolled.',
            'data' => [
                'enrolled' => true,
                'gallery_size' => (int) ($result['data']['gallery_size'] ?? 0),
                'poses_learned' => (int) ($result['data']['poses_learned'] ?? 1),
            ],
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>, http?: int}
     */
    public function verifySelfie(
        WhatsappWallet $wallet,
        UploadedFile $selfie,
        string $kind,
        ?string $accountNumber = null,
        ?string $bankCode = null,
        ?string $toPhone = null,
        ?float $amount = null,
    ): array {
        if (! $this->isEnabled()) {
            return ['ok' => false, 'message' => 'Face check is not available.', 'http' => 503];
        }

        $status = $this->status($wallet);
        if (! $status['enrolled']) {
            return [
                'ok' => false,
                'message' => 'Enroll your face before verifying.',
                'http' => 422,
                'data' => ['face_enrolled' => false, 'error_code' => 'face_enrollment_required'],
            ];
        }

        $result = $this->client->verifyFace($this->checkfaceUserId($wallet), $selfie);
        $authenticated = (bool) ($result['data']['authenticated'] ?? false);
        if (! $result['ok'] || ! $authenticated) {
            return [
                'ok' => false,
                'message' => $result['message'] ?: 'Face verification failed.',
                'http' => $result['status'] === 404 ? 422 : ($result['status'] >= 400 ? $result['status'] : 422),
                'data' => array_merge($result['data'], ['error_code' => 'face_mismatch']),
            ];
        }

        $challenge = $this->issueChallenge($wallet, $kind, $accountNumber, $bankCode, $toPhone, $amount);

        return [
            'ok' => true,
            'message' => 'Face verified.',
            'data' => array_merge($challenge, [
                'confidence_score' => $result['data']['confidence_score'] ?? null,
                'liveness_checked' => false,
            ]),
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>, http?: int}
     */
    public function startLiveness(WhatsappWallet $wallet): array
    {
        if (! $this->isEnabled()) {
            return ['ok' => false, 'message' => 'Face check is not available.', 'http' => 503];
        }

        $status = $this->status($wallet);
        if (! $status['enrolled']) {
            return [
                'ok' => false,
                'message' => 'Enroll your face before starting liveness.',
                'http' => 422,
                'data' => ['face_enrolled' => false, 'error_code' => 'face_enrollment_required'],
            ];
        }

        $result = $this->client->createLivenessSession($this->checkfaceUserId($wallet));
        if (! $result['ok'] || empty($result['data']['session_id'])) {
            return [
                'ok' => false,
                'message' => $result['message'] ?: 'Could not start liveness.',
                'http' => $result['status'] >= 400 ? $result['status'] : 422,
            ];
        }

        return [
            'ok' => true,
            'message' => 'Liveness session created.',
            'data' => [
                'session_id' => (string) $result['data']['session_id'],
                'challenges' => $result['data']['challenges'] ?? [],
                'expires_in' => $result['data']['expires_in'] ?? null,
                'capture' => $result['data']['capture'] ?? 'video',
                'seconds_per_challenge' => $result['data']['seconds_per_challenge'] ?? null,
            ],
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>, http?: int}
     */
    public function completeLiveness(
        WhatsappWallet $wallet,
        string $sessionId,
        UploadedFile $clip,
        string $kind,
        ?string $accountNumber = null,
        ?string $bankCode = null,
        ?string $toPhone = null,
        ?float $amount = null,
    ): array {
        if (! $this->isEnabled()) {
            return ['ok' => false, 'message' => 'Face check is not available.', 'http' => 503];
        }

        $result = $this->client->submitLivenessVideo($sessionId, $clip);
        $passed = (bool) ($result['data']['authenticated'] ?? $result['data']['passed'] ?? false);
        if (! $result['ok'] || ! $passed) {
            return [
                'ok' => false,
                'message' => $result['message'] ?: 'Liveness check failed.',
                'http' => $result['status'] >= 400 ? $result['status'] : 422,
                'data' => array_merge($result['data'], ['error_code' => 'liveness_failed']),
            ];
        }

        $challenge = $this->issueChallenge($wallet, $kind, $accountNumber, $bankCode, $toPhone, $amount);

        return [
            'ok' => true,
            'message' => 'Liveness passed.',
            'data' => array_merge($challenge, [
                'liveness_checked' => true,
                'liveness_score' => $result['data']['liveness_score'] ?? $result['data']['liveness_percent'] ?? null,
            ]),
        ];
    }

    /**
     * Whether this outbound transfer needs a fresh face challenge.
     */
    public function requiresFaceCheck(
        WhatsappWallet $wallet,
        float $amount,
        string $kind,
        ?string $accountNumber = null,
        ?string $bankCode = null,
        ?string $toPhone = null,
    ): bool {
        if (! $this->isEnabled()) {
            return false;
        }

        if ($amount < $this->amountThreshold()) {
            return false;
        }

        return ! $this->isTrustedRecipient($wallet, $kind, $accountNumber, $bankCode, $toPhone);
    }

    public function isTrustedRecipient(
        WhatsappWallet $wallet,
        string $kind,
        ?string $accountNumber = null,
        ?string $bankCode = null,
        ?string $toPhone = null,
    ): bool {
        $dest = $this->destinationKey($kind, $accountNumber, $bankCode, $toPhone);
        if ($dest === null) {
            return false;
        }

        $saved = WhatsappWalletTransferBeneficiary::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->where('destination_key', $dest)
            ->exists();
        if ($saved) {
            return true;
        }

        return $this->frequentSendCount($wallet, $kind, $accountNumber, $bankCode, $toPhone)
            >= (int) config('checkface.frequent_min_transfers', 3);
    }

    /**
     * Gate a transfer request. Returns a JsonResponse when blocked; null when OK / not required.
     */
    public function rejectIfRequired(
        WhatsappWallet $wallet,
        float $amount,
        string $kind,
        ?string $faceToken,
        ?string $accountNumber = null,
        ?string $bankCode = null,
        ?string $toPhone = null,
    ): ?JsonResponse {
        if (! $this->requiresFaceCheck($wallet, $amount, $kind, $accountNumber, $bankCode, $toPhone)) {
            return null;
        }

        $status = $this->status($wallet);
        $payload = [
            'face_check_required' => true,
            'face_enrolled' => $status['enrolled'],
            'threshold_ngn' => $this->amountThreshold(),
            'trusted_recipient' => false,
            'error_code' => $status['enrolled'] ? 'face_check_required' : 'face_enrollment_required',
        ];

        if (! $status['enrolled']) {
            return response()->json([
                'success' => false,
                'message' => 'Enroll your face to send ₦'.number_format($this->amountThreshold(), 0).' or more to new recipients.',
                'error_code' => 'face_enrollment_required',
                'data' => $payload,
            ], 403);
        }

        $token = trim((string) $faceToken);
        if ($token === '' || ! $this->consumeChallenge($token, $wallet, $kind, $accountNumber, $bankCode, $toPhone, $amount)) {
            return response()->json([
                'success' => false,
                'message' => 'Face verification required for this transfer. Verify with a selfie, then retry with face_token.',
                'error_code' => 'face_check_required',
                'data' => $payload,
            ], 403);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function feeQuoteFaceMeta(
        WhatsappWallet $wallet,
        float $amount,
        string $kind,
        ?string $accountNumber = null,
        ?string $bankCode = null,
        ?string $toPhone = null,
    ): array {
        $required = $this->requiresFaceCheck($wallet, $amount, $kind, $accountNumber, $bankCode, $toPhone);
        $status = $this->status($wallet);

        return [
            'face_check_required' => $required,
            'face_enrolled' => $status['enrolled'],
            'face_check_enabled' => $this->isEnabled(),
            'face_threshold_ngn' => $this->amountThreshold(),
            'trusted_recipient' => $this->isTrustedRecipient($wallet, $kind, $accountNumber, $bankCode, $toPhone),
        ];
    }

    /**
     * @return array{face_token: string, expires_at: string, expires_in: int}
     */
    private function issueChallenge(
        WhatsappWallet $wallet,
        string $kind,
        ?string $accountNumber,
        ?string $bankCode,
        ?string $toPhone,
        ?float $amount,
    ): array {
        $ttl = max(1, (int) config('checkface.challenge_ttl_minutes', 5));
        $token = 'ftok_'.Str::random(48);
        $dest = $this->destinationKey($kind, $accountNumber, $bankCode, $toPhone);

        Cache::put(self::CACHE_PREFIX.$token, [
            'wallet_id' => (int) $wallet->id,
            'kind' => strtolower(trim($kind)),
            'destination_key' => $dest,
            'amount' => $amount !== null ? round($amount, 2) : null,
            'issued_at' => now()->toIso8601String(),
        ], now()->addMinutes($ttl));

        return [
            'face_token' => $token,
            'expires_at' => now()->addMinutes($ttl)->toIso8601String(),
            'expires_in' => $ttl * 60,
        ];
    }

    private function consumeChallenge(
        string $token,
        WhatsappWallet $wallet,
        string $kind,
        ?string $accountNumber,
        ?string $bankCode,
        ?string $toPhone,
        float $amount,
    ): bool {
        $key = self::CACHE_PREFIX.$token;
        $payload = Cache::pull($key);
        if (! is_array($payload)) {
            return false;
        }

        if ((int) ($payload['wallet_id'] ?? 0) !== (int) $wallet->id) {
            return false;
        }

        if (strtolower((string) ($payload['kind'] ?? '')) !== strtolower(trim($kind))) {
            return false;
        }

        $expectedDest = $this->destinationKey($kind, $accountNumber, $bankCode, $toPhone);
        $tokenDest = $payload['destination_key'] ?? null;
        // Token may be unbound (null dest) when app verified before picking recipient — allow same wallet only.
        if ($tokenDest !== null && $expectedDest !== null && ! hash_equals((string) $tokenDest, $expectedDest)) {
            return false;
        }

        $tokenAmount = $payload['amount'] ?? null;
        if ($tokenAmount !== null && abs((float) $tokenAmount - round($amount, 2)) > 0.009) {
            // Allow token issued without amount, or with matching amount.
            return false;
        }

        return true;
    }

    private function destinationKey(
        string $kind,
        ?string $accountNumber,
        ?string $bankCode,
        ?string $toPhone,
    ): ?string {
        $kind = strtolower(trim($kind));
        if ($kind === WhatsappWalletTransferBeneficiary::KIND_BANK) {
            $acct = preg_replace('/\D+/', '', (string) $accountNumber) ?? '';
            $code = trim((string) $bankCode);
            if (strlen($acct) !== 10 || $code === '') {
                return null;
            }

            return WhatsappWalletTransferBeneficiary::bankDestinationKey($code, $acct);
        }

        if ($kind === WhatsappWalletTransferBeneficiary::KIND_P2P) {
            $e164 = PhoneNormalizer::canonicalAuthE164Digits((string) $toPhone)
                ?? preg_replace('/\D+/', '', (string) $toPhone);
            if ($e164 === null || $e164 === '') {
                return null;
            }

            return WhatsappWalletTransferBeneficiary::p2pDestinationKey((string) $e164);
        }

        return null;
    }

    private function frequentSendCount(
        WhatsappWallet $wallet,
        string $kind,
        ?string $accountNumber,
        ?string $bankCode,
        ?string $toPhone,
    ): int {
        $kind = strtolower(trim($kind));
        $lookback = (int) config('checkface.frequent_lookback_days', 365);

        $query = WhatsappWalletTransaction::query()
            ->where('whatsapp_wallet_id', $wallet->id);

        if ($lookback > 0) {
            $query->where('created_at', '>=', now()->subDays($lookback));
        }

        if ($kind === WhatsappWalletTransferBeneficiary::KIND_BANK) {
            $acct = preg_replace('/\D+/', '', (string) $accountNumber) ?? '';
            $code = trim((string) $bankCode);
            if (strlen($acct) !== 10 || $code === '') {
                return 0;
            }

            $rows = $query
                ->where('type', WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT)
                ->where('counterparty_account_number', $acct)
                ->where('counterparty_bank_code', $code)
                ->limit(50)
                ->get(['meta']);

            return $rows->filter(static fn (WhatsappWalletTransaction $tx): bool => ! $tx->isReversed())->count();
        }

        if ($kind === WhatsappWalletTransferBeneficiary::KIND_P2P) {
            $e164 = PhoneNormalizer::canonicalAuthE164Digits((string) $toPhone)
                ?? preg_replace('/\D+/', '', (string) $toPhone);
            if ($e164 === null || $e164 === '') {
                return 0;
            }

            return (int) $query
                ->where('type', WhatsappWalletTransaction::TYPE_P2P_DEBIT)
                ->where('counterparty_phone_e164', $e164)
                ->count();
        }

        return 0;
    }
}
