<?php

namespace App\Services\Consumer;

use App\Exceptions\WebAuthnNotConfiguredException;
use App\Models\ConsumerPasskeyCredential;
use App\Models\ConsumerTrustedDevice;
use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\Exception\AuthenticatorResponseVerificationException;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Cose\Algorithms;

class ConsumerWebAuthnService
{
    private const CACHE_REG = 'consumer_webauthn_reg:';

    private const CACHE_LOGIN = 'consumer_webauthn_login:';

    private const CACHE_TX = 'consumer_webauthn_tx:';

    /** @var list<string> */
    public const TRANSACTION_ACTIONS = [
        'transfer_p2p',
        'transfer_bank',
        'card_details',
        'card_topup',
        'card_withdraw',
        'card_status',
        'savings_deposit',
        'savings_withdraw',
        'vtu_airtime',
        'vtu_data',
        'vtu_electricity',
        'vtu_tv',
        'vtu_betting',
        'wallet_transfer_web',
    ];

    private ?SerializerInterface $serializer = null;

    private ?AuthenticatorAttestationResponseValidator $attestationValidator = null;

    private ?AuthenticatorAssertionResponseValidator $assertionValidator = null;

    public static function isAvailable(): bool
    {
        return class_exists('Webauthn\\AttestationStatement\\AttestationStatementSupportManager')
            && class_exists('Webauthn\\Denormalizer\\WebauthnSerializerFactory')
            && class_exists('Cose\\Algorithms')
            && class_exists('Symfony\\Component\\Serializer\\Serializer');
    }

    public function isEnabled(): bool
    {
        return (bool) config('consumer_wallet.device_trust_enabled', true);
    }

    /**
     * @return array{ok: bool, message?: string, options?: array<string, mixed>}
     */
    public function registerOptions(ConsumerWalletApiAccount $account, ?string $deviceName = null): array
    {
        if (! $this->isEnabled()) {
            return ['ok' => false, 'message' => 'Device trust is disabled.'];
        }

        try {
            $this->ensureInitialized();
        } catch (WebAuthnNotConfiguredException $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'unavailable' => true];
        }

        $account->loadMissing('wallet');
        $wallet = $account->wallet;
        if (! $wallet) {
            return ['ok' => false, 'message' => 'Wallet not linked.'];
        }

        $challenge = random_bytes(32);
        $userHandle = $this->userHandleForAccount($account);
        $exclude = $this->existingCredentialDescriptors($account);

        $options = PublicKeyCredentialCreationOptions::create(
            PublicKeyCredentialRpEntity::create($this->rpName(), $this->rpId()),
            PublicKeyCredentialUserEntity::create(
                (string) $account->phone_e164,
                $userHandle,
                $this->displayNameForWallet($wallet),
            ),
            $challenge,
            [
                PublicKeyCredentialParameters::createPk(Algorithms::COSE_ALGORITHM_ES256),
            ],
            AuthenticatorSelectionCriteria::create(
                authenticatorAttachment: AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_PLATFORM,
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
                residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
            ),
            attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            excludeCredentials: $exclude,
            timeout: 120_000,
        );

        Cache::put($this->regCacheKey($account->id), [
            'options' => $this->optionsToArray($options),
            'device_name' => $deviceName,
        ], now()->addMinutes(5));

        return [
            'ok' => true,
            'options' => $this->optionsToArray($options),
        ];
    }

    /**
     * @param  array<string, mixed>  $credentialPayload
     * @return array{ok: bool, message?: string, credential_id?: string, device_id?: int}
     */
    public function registerVerify(
        ConsumerWalletApiAccount $account,
        array $credentialPayload,
        string $platform,
        ?string $deviceName = null,
    ): array {
        if (! $this->isEnabled()) {
            return ['ok' => false, 'message' => 'Device trust is disabled.'];
        }

        try {
            $this->ensureInitialized();
        } catch (WebAuthnNotConfiguredException $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'unavailable' => true];
        }

        $cached = Cache::get($this->regCacheKey($account->id));
        if (! is_array($cached) || ! isset($cached['options']) || ! is_array($cached['options'])) {
            return ['ok' => false, 'message' => 'Registration session expired. Request new options.'];
        }

        try {
            $credentialPayload = $this->normalizeCredentialPayload($credentialPayload);
            /** @var PublicKeyCredential $publicKeyCredential */
            $publicKeyCredential = $this->serializer()->denormalize(
                $credentialPayload,
                PublicKeyCredential::class,
                'json'
            );
            $response = $publicKeyCredential->response;
            if (! $response instanceof AuthenticatorAttestationResponse) {
                return ['ok' => false, 'message' => 'Invalid passkey response.'];
            }

            /** @var PublicKeyCredentialCreationOptions $options */
            $options = $this->serializer()->denormalize(
                $cached['options'],
                PublicKeyCredentialCreationOptions::class,
                'json'
            );

            $record = $this->attestationValidator()->check($response, $options, $this->rpId());
            Cache::forget($this->regCacheKey($account->id));

            $label = trim((string) ($deviceName ?: ($cached['device_name'] ?? '')));
            if ($label === '') {
                $label = ucfirst($platform).' device';
            }

            $device = ConsumerTrustedDevice::query()->create([
                'consumer_wallet_api_account_id' => $account->id,
                'label' => Str::limit($label, 120, ''),
                'platform' => Str::limit($platform, 32, ''),
                'last_active_at' => now(),
            ]);

            $stored = $this->credentialRecordToStorage($record);
            ConsumerPasskeyCredential::query()->create([
                'consumer_trusted_device_id' => $device->id,
                'credential_id' => base64_encode($record->publicKeyCredentialId),
                'credential_record' => $stored,
                'counter' => $record->counter,
            ]);

            return [
                'ok' => true,
                'credential_id' => base64_encode($record->publicKeyCredentialId),
                'device_id' => $device->id,
            ];
        } catch (AuthenticatorResponseVerificationException $e) {
            $this->logVerificationFailure('register', $account->id, $e, $credentialPayload);

            return ['ok' => false, 'message' => 'Passkey verification failed.'];
        } catch (\Throwable $e) {
            Log::warning('consumer_webauthn.register_verify_error', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => 'Could not register passkey.'];
        }
    }

    /**
     * @return array{ok: bool, message?: string, options?: array<string, mixed>}
     */
    public function loginOptions(string $phoneInput): array
    {
        if (! $this->isEnabled()) {
            return ['ok' => false, 'message' => 'Device trust is disabled.'];
        }

        try {
            $this->ensureInitialized();
        } catch (WebAuthnNotConfiguredException $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'unavailable' => true];
        }

        $account = app(ConsumerDeviceTrustService::class)->accountForPhone($phoneInput);
        if (! $account) {
            return ['ok' => false, 'message' => 'No passkey registered for this number.'];
        }

        $credentials = $this->existingCredentialDescriptors($account);
        if ($credentials === []) {
            return ['ok' => false, 'message' => 'No passkey registered for this number.'];
        }

        $challenge = random_bytes(32);
        $options = PublicKeyCredentialRequestOptions::create(
            $challenge,
            $this->rpId(),
            $credentials,
            PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            120_000,
        );

        Cache::put($this->loginCacheKey((string) $account->phone_e164), [
            'options' => $this->optionsToArray($options),
            'account_id' => $account->id,
        ], now()->addMinutes(5));

        return [
            'ok' => true,
            'options' => $this->optionsToArray($options),
        ];
    }

    /**
     * @param  array<string, mixed>  $credentialPayload
     * @return array{ok: bool, message?: string, account?: ConsumerWalletApiAccount, same_device?: bool}
     */
    public function loginVerify(string $phoneInput, array $credentialPayload): array
    {
        if (! $this->isEnabled()) {
            return ['ok' => false, 'message' => 'Device trust is disabled.'];
        }

        try {
            $this->ensureInitialized();
        } catch (WebAuthnNotConfiguredException $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'unavailable' => true];
        }

        $account = app(ConsumerDeviceTrustService::class)->accountForPhone($phoneInput);
        if (! $account) {
            return ['ok' => false, 'message' => 'No passkey registered for this number.'];
        }

        $cached = Cache::get($this->loginCacheKey((string) $account->phone_e164));
        if (! is_array($cached) || ! isset($cached['options']) || ! is_array($cached['options'])) {
            return ['ok' => false, 'message' => 'Login session expired. Request new options.'];
        }

        try {
            $credentialPayload = $this->normalizeCredentialPayload($credentialPayload);
            /** @var PublicKeyCredential $publicKeyCredential */
            $publicKeyCredential = $this->serializer()->denormalize(
                $credentialPayload,
                PublicKeyCredential::class,
                'json'
            );
            $response = $publicKeyCredential->response;
            if (! $response instanceof AuthenticatorAssertionResponse) {
                return ['ok' => false, 'message' => 'Invalid passkey response.'];
            }

            $storedCredential = $this->findStoredCredential($account, $publicKeyCredential->rawId);
            if ($storedCredential === null) {
                return ['ok' => false, 'message' => 'Unknown passkey.'];
            }

            $record = $this->credentialRecordFromStorage($storedCredential->credential_record);
            /** @var PublicKeyCredentialRequestOptions $options */
            $options = $this->serializer()->denormalize(
                $cached['options'],
                PublicKeyCredentialRequestOptions::class,
                'json'
            );

            $updated = $this->assertionValidator()->check(
                $record,
                $response,
                $options,
                $this->rpId(),
                $record->userHandle,
            );

            $storedCredential->counter = $updated->counter;
            $storedCredential->credential_record = $this->credentialRecordToStorage($updated);
            $storedCredential->save();

            $device = $storedCredential->device;
            if ($device) {
                $device->last_active_at = now();
                $device->save();
            }

            Cache::forget($this->loginCacheKey((string) $account->phone_e164));

            return [
                'ok' => true,
                'account' => $account->fresh(),
                'same_device' => true,
            ];
        } catch (AuthenticatorResponseVerificationException $e) {
            $this->logVerificationFailure('login', $account->id, $e, $credentialPayload);

            return ['ok' => false, 'message' => 'Passkey verification failed.'];
        } catch (\Throwable $e) {
            Log::warning('consumer_webauthn.login_verify_error', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => 'Could not verify passkey.'];
        }
    }

    /**
     * @param  array<string, mixed>  $intent
     * @return array{ok: bool, message?: string, options?: array<string, mixed>, unavailable?: bool}
     */
    public function transactionOptions(ConsumerWalletApiAccount $account, array $intent): array
    {
        if (! $this->isEnabled()) {
            return ['ok' => false, 'message' => 'Device trust is disabled.'];
        }

        if ($this->normalizeTransactionIntent($intent) === null) {
            return ['ok' => false, 'message' => 'Unsupported payment action.'];
        }

        try {
            $this->ensureInitialized();
        } catch (WebAuthnNotConfiguredException $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'unavailable' => true];
        }

        $credentials = $this->existingCredentialDescriptors($account);
        if ($credentials === []) {
            return ['ok' => false, 'message' => 'No passkey registered on this account.'];
        }

        $challenge = random_bytes(32);
        $options = PublicKeyCredentialRequestOptions::create(
            $challenge,
            $this->rpId(),
            $credentials,
            PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            120_000,
        );

        $challengeToken = (string) Str::uuid();
        Cache::put($this->txCacheKey($challengeToken), [
            'options' => $this->optionsToArray($options),
            'account_id' => $account->id,
            'intent_hash' => self::intentHash($intent),
        ], now()->addMinutes(5));

        $payload = $this->optionsToArray($options);
        $payload['challenge_token'] = $challengeToken;

        return [
            'ok' => true,
            'options' => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $credentialPayload
     * @param  array<string, mixed>  $intent
     * @return array{ok: bool, message?: string, payment_token?: string, expires_at?: string, unavailable?: bool}
     */
    public function transactionVerify(
        ConsumerWalletApiAccount $account,
        string $challengeToken,
        array $credentialPayload,
        array $intent,
    ): array {
        if (! $this->isEnabled()) {
            return ['ok' => false, 'message' => 'Device trust is disabled.'];
        }

        if ($this->normalizeTransactionIntent($intent) === null) {
            return ['ok' => false, 'message' => 'Unsupported payment action.'];
        }

        try {
            $this->ensureInitialized();
        } catch (WebAuthnNotConfiguredException $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'unavailable' => true];
        }

        $cached = Cache::get($this->txCacheKey($challengeToken));
        if (! is_array($cached)
            || (int) ($cached['account_id'] ?? 0) !== (int) $account->id
            || ! isset($cached['options']) || ! is_array($cached['options'])
            || ! hash_equals((string) ($cached['intent_hash'] ?? ''), self::intentHash($intent))) {
            return ['ok' => false, 'message' => 'Payment authorization session expired. Try again.'];
        }

        $assertion = $this->verifyAssertionForAccount($account, $cached['options'], $credentialPayload, 'transaction');
        if (! ($assertion['ok'] ?? false)) {
            return $assertion;
        }

        Cache::forget($this->txCacheKey($challengeToken));

        $account->loadMissing('wallet');
        $walletId = (int) ($account->wallet?->id ?? $account->whatsapp_wallet_id ?? 0);
        if ($walletId <= 0) {
            return ['ok' => false, 'message' => 'Wallet not linked.'];
        }

        $issued = app(ConsumerPaymentAuthService::class)->issuePaymentToken(
            (int) $account->id,
            $walletId,
            $intent,
        );

        return [
            'ok' => true,
            'payment_token' => $issued['payment_token'],
            'expires_at' => $issued['expires_at'],
        ];
    }

    /**
     * @param  array<string, mixed>  $intent
     */
    public static function intentHash(array $intent): string
    {
        $normalized = $intent;
        ksort($normalized);

        return hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  array<string, mixed>  $intent
     */
    private function normalizeTransactionIntent(array $intent): ?string
    {
        $action = trim((string) ($intent['action'] ?? ''));
        if ($action === '' || ! in_array($action, self::TRANSACTION_ACTIONS, true)) {
            return null;
        }

        return $action;
    }

    /**
     * @param  array<string, mixed>  $credentialPayload
     * @return array{ok: bool, message?: string}
     */
    private function verifyAssertionForAccount(
        ConsumerWalletApiAccount $account,
        array $cachedOptions,
        array $credentialPayload,
        string $flow,
    ): array {
        try {
            $credentialPayload = $this->normalizeCredentialPayload($credentialPayload);
            /** @var PublicKeyCredential $publicKeyCredential */
            $publicKeyCredential = $this->serializer()->denormalize(
                $credentialPayload,
                PublicKeyCredential::class,
                'json'
            );
            $response = $publicKeyCredential->response;
            if (! $response instanceof AuthenticatorAssertionResponse) {
                return ['ok' => false, 'message' => 'Invalid passkey response.'];
            }

            $storedCredential = $this->findStoredCredential($account, $publicKeyCredential->rawId);
            if ($storedCredential === null) {
                return ['ok' => false, 'message' => 'Unknown passkey.'];
            }

            $record = $this->credentialRecordFromStorage($storedCredential->credential_record);
            /** @var PublicKeyCredentialRequestOptions $options */
            $options = $this->serializer()->denormalize(
                $cachedOptions,
                PublicKeyCredentialRequestOptions::class,
                'json'
            );

            $updated = $this->assertionValidator()->check(
                $record,
                $response,
                $options,
                $this->rpId(),
                $record->userHandle,
            );

            $storedCredential->counter = $updated->counter;
            $storedCredential->credential_record = $this->credentialRecordToStorage($updated);
            $storedCredential->save();

            $device = $storedCredential->device;
            if ($device) {
                $device->last_active_at = now();
                $device->save();
            }

            return ['ok' => true];
        } catch (AuthenticatorResponseVerificationException $e) {
            $this->logVerificationFailure($flow, $account->id, $e, $credentialPayload);

            return ['ok' => false, 'message' => 'Passkey verification failed.'];
        } catch (\Throwable $e) {
            Log::warning('consumer_webauthn.'.$flow.'_verify_error', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => 'Could not verify passkey.'];
        }
    }

    private function txCacheKey(string $challengeToken): string
    {
        return self::CACHE_TX.$challengeToken;
    }

    /**
     * @param  array<string, mixed>  $credentialPayload
     */
    public function verifyCredentialForBind(
        ConsumerWalletApiAccount $account,
        array $credentialPayload,
        string $platform,
        ?string $deviceName,
        bool $allowExisting = false,
    ): array {
        $result = $this->registerVerify($account, $credentialPayload, $platform, $deviceName);
        if ($result['ok']) {
            return $result;
        }

        if (! $allowExisting) {
            return $result;
        }

        return $this->loginVerify((string) $account->phone_e164, $credentialPayload);
    }

    private function ensureInitialized(): void
    {
        if ($this->serializer !== null) {
            return;
        }

        if (! static::isAvailable()) {
            throw WebAuthnNotConfiguredException::missingPackages();
        }

        $attestationManager = AttestationStatementSupportManager::create();
        $this->serializer = (new WebauthnSerializerFactory($attestationManager))->create();

        $ceremonyFactory = new CeremonyStepManagerFactory();
        $ceremonyFactory->setAttestationStatementSupportManager($attestationManager);
        $this->configureCeremonyOrigins($ceremonyFactory);

        $this->attestationValidator = AuthenticatorAttestationResponseValidator::create(
            $ceremonyFactory->creationCeremony()
        );
        $this->assertionValidator = AuthenticatorAssertionResponseValidator::create(
            $ceremonyFactory->requestCeremony()
        );
    }

    private function configureCeremonyOrigins(CeremonyStepManagerFactory $ceremonyFactory): void
    {
        $origins = $this->allowedOrigins();
        $rpOrigin = 'https://'.$this->rpId();
        if (! in_array($rpOrigin, $origins, true)) {
            $origins[] = $rpOrigin;
        }

        if ($origins !== []) {
            $ceremonyFactory->setAllowedOrigins($origins, true);
        } else {
            $ceremonyFactory->setSecuredRelyingPartyId([$this->rpId()]);
        }
    }

    private function serializer(): SerializerInterface
    {
        $this->ensureInitialized();

        return $this->serializer;
    }

    private function attestationValidator(): AuthenticatorAttestationResponseValidator
    {
        $this->ensureInitialized();

        return $this->attestationValidator;
    }

    private function assertionValidator(): AuthenticatorAssertionResponseValidator
    {
        $this->ensureInitialized();

        return $this->assertionValidator;
    }

    /**
     * @return array<string, mixed>
     */
    private function optionsToArray(PublicKeyCredentialCreationOptions|PublicKeyCredentialRequestOptions $options): array
    {
        /** @var array<string, mixed> $encoded */
        $encoded = $this->serializer()->normalize($options, 'json');

        return $encoded;
    }

    /**
     * @return PublicKeyCredentialDescriptor[]
     */
    private function existingCredentialDescriptors(ConsumerWalletApiAccount $account): array
    {
        $descriptors = [];
        $account->loadMissing('trustedDevices.passkey');
        foreach ($account->trustedDevices as $device) {
            if ($device->passkey === null) {
                continue;
            }
            $record = $this->credentialRecordFromStorage($device->passkey->credential_record);
            $descriptors[] = $record->getPublicKeyCredentialDescriptor();
        }

        return $descriptors;
    }

    private function findStoredCredential(ConsumerWalletApiAccount $account, string $rawId): ?ConsumerPasskeyCredential
    {
        $encoded = base64_encode($rawId);
        $account->loadMissing('trustedDevices.passkey');

        foreach ($account->trustedDevices as $device) {
            if ($device->passkey && hash_equals((string) $device->passkey->credential_id, $encoded)) {
                return $device->passkey;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function credentialRecordToStorage(CredentialRecord $record): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->serializer()->normalize($record, 'json');

        return $data;
    }

    /**
     * @param  array<string, mixed>  $storage
     */
    private function credentialRecordFromStorage(array $storage): CredentialRecord
    {
        /** @var CredentialRecord $record */
        $record = $this->serializer()->denormalize($storage, CredentialRecord::class, 'json');

        return $record;
    }

    private function userHandleForAccount(ConsumerWalletApiAccount $account): string
    {
        return hash('sha256', 'consumer-wallet:'.$account->id, true);
    }

    private function displayNameForWallet(?WhatsappWallet $wallet): string
    {
        if (! $wallet) {
            return 'CheckoutNow user';
        }

        $name = trim(trim((string) $wallet->kyc_fname).' '.trim((string) $wallet->kyc_lname));
        if ($name !== '') {
            return Str::limit($name, 64, '');
        }

        return Str::limit((string) ($wallet->sender_name ?: $wallet->phone_e164), 64, '');
    }

    private function rpId(): string
    {
        return (string) config('consumer_wallet.webauthn_rp_id', 'check-outpay.com');
    }

    private function rpName(): string
    {
        return (string) config('consumer_wallet.webauthn_rp_name', 'CheckoutNow');
    }

    private function regCacheKey(int $accountId): string
    {
        return self::CACHE_REG.$accountId;
    }

    private function loginCacheKey(string $phoneE164): string
    {
        return self::CACHE_LOGIN.hash('sha256', $phoneE164);
    }

    /**
     * @return string[]
     */
    private function allowedOrigins(): array
    {
        $origins = config('consumer_wallet.webauthn_allowed_origins', []);
        $origins = is_array($origins) ? array_values(array_filter($origins)) : [];

        foreach ((array) config('consumer_wallet.webauthn_android_apk_key_hashes', []) as $hash) {
            $hash = trim((string) $hash);
            if ($hash === '') {
                continue;
            }
            $origins[] = str_starts_with($hash, 'android:apk-key-hash:')
                ? $hash
                : 'android:apk-key-hash:'.$hash;
        }

        // Derive Android WebAuthn origins from Digital Asset Links cert fingerprints
        // so native verify works without duplicating CONSUMER_WEBAUTHN_ANDROID_APK_KEY_HASHES.
        foreach ((array) config('consumer_wallet.android_assetlinks_sha256_fingerprints', []) as $fp) {
            $apkHash = self::sha256FingerprintToApkKeyHash((string) $fp);
            if ($apkHash !== null) {
                $origins[] = 'android:apk-key-hash:'.$apkHash;
            }
        }

        return array_values(array_unique($origins));
    }

    /**
     * Convert colon-separated SHA-256 cert fingerprint → Base64URL for android:apk-key-hash.
     */
    public static function sha256FingerprintToApkKeyHash(string $fingerprint): ?string
    {
        $hex = strtolower(preg_replace('/[^0-9a-f]/i', '', $fingerprint) ?? '');
        if ($hex === '' || strlen($hex) !== 64 || ! ctype_xdigit($hex)) {
            return null;
        }

        $binary = hex2bin($hex);
        if ($binary === false) {
            return null;
        }

        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    /**
     * @param  array<string, mixed>  $credentialPayload
     * @return array<string, mixed>
     */
    private function normalizeCredentialPayload(array $credentialPayload): array
    {
        if (! isset($credentialPayload['type'])) {
            $credentialPayload['type'] = 'public-key';
        }

        return $credentialPayload;
    }

    /**
     * @param  array<string, mixed>  $credentialPayload
     */
    private function logVerificationFailure(
        string $flow,
        int $accountId,
        AuthenticatorResponseVerificationException $e,
        array $credentialPayload,
    ): void {
        Log::warning('consumer_webauthn.verification_failed', [
            'flow' => $flow,
            'account_id' => $accountId,
            'reason' => $e->getMessage(),
            'client_origin' => $this->clientOriginFromPayload($credentialPayload),
            'allowed_origins' => $this->allowedOrigins(),
            'rp_id' => $this->rpId(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $credentialPayload
     */
    private function clientOriginFromPayload(array $credentialPayload): ?string
    {
        try {
            $response = $credentialPayload['response'] ?? null;
            if (! is_array($response) || ! isset($response['clientDataJSON']) || ! is_string($response['clientDataJSON'])) {
                return null;
            }

            $decoded = Base64UrlSafe::decodeNoPadding($response['clientDataJSON']);
            $json = json_decode($decoded, true);

            return is_array($json) && is_string($json['origin'] ?? null) ? $json['origin'] : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
