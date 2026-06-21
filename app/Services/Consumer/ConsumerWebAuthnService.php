<?php

namespace App\Services\Consumer;

use App\Models\ConsumerPasskeyCredential;
use App\Models\ConsumerTrustedDevice;
use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
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

    private SerializerInterface $serializer;

    private AuthenticatorAttestationResponseValidator $attestationValidator;

    private AuthenticatorAssertionResponseValidator $assertionValidator;

    public function __construct()
    {
        $attestationManager = AttestationStatementSupportManager::create();
        $attestationManager->add(NoneAttestationStatementSupport::create());

        $this->serializer = (new WebauthnSerializerFactory($attestationManager))->create();

        $ceremonyFactory = new CeremonyStepManagerFactory();
        $ceremonyFactory->setSecuredRelyingPartyId([$this->rpId()]);

        $this->attestationValidator = AuthenticatorAttestationResponseValidator::create(
            $ceremonyFactory->creationCeremony()
        );
        $this->assertionValidator = AuthenticatorAssertionResponseValidator::create(
            $ceremonyFactory->requestCeremony()
        );
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
            'challenge' => $options->challenge,
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

        $cached = Cache::get($this->regCacheKey($account->id));
        if (! is_array($cached) || ! isset($cached['challenge'])) {
            return ['ok' => false, 'message' => 'Registration session expired. Request new options.'];
        }

        try {
            /** @var PublicKeyCredential $publicKeyCredential */
            $publicKeyCredential = $this->serializer->denormalize(
                $credentialPayload,
                PublicKeyCredential::class,
                'json'
            );
            $response = $publicKeyCredential->response;
            if (! $response instanceof AuthenticatorAttestationResponse) {
                return ['ok' => false, 'message' => 'Invalid passkey response.'];
            }

            $options = PublicKeyCredentialCreationOptions::create(
                PublicKeyCredentialRpEntity::create($this->rpName(), $this->rpId()),
                PublicKeyCredentialUserEntity::create(
                    (string) $account->phone_e164,
                    $this->userHandleForAccount($account),
                    $this->displayNameForWallet($account->wallet),
                ),
                (string) $cached['challenge'],
            );

            $record = $this->attestationValidator->check($response, $options, $this->rpId());
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
            return ['ok' => false, 'message' => 'Passkey verification failed.'];
        } catch (\Throwable) {
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
            'challenge' => $options->challenge,
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

        $account = app(ConsumerDeviceTrustService::class)->accountForPhone($phoneInput);
        if (! $account) {
            return ['ok' => false, 'message' => 'No passkey registered for this number.'];
        }

        $cached = Cache::get($this->loginCacheKey((string) $account->phone_e164));
        if (! is_array($cached) || ! isset($cached['challenge'])) {
            return ['ok' => false, 'message' => 'Login session expired. Request new options.'];
        }

        try {
            /** @var PublicKeyCredential $publicKeyCredential */
            $publicKeyCredential = $this->serializer->denormalize(
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
            $options = PublicKeyCredentialRequestOptions::create(
                (string) $cached['challenge'],
                $this->rpId(),
                [$record->getPublicKeyCredentialDescriptor()],
                PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            );

            $updated = $this->assertionValidator->check(
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
        } catch (AuthenticatorResponseVerificationException) {
            return ['ok' => false, 'message' => 'Passkey verification failed.'];
        } catch (\Throwable) {
            return ['ok' => false, 'message' => 'Could not verify passkey.'];
        }
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

    /**
     * @return array<string, mixed>
     */
    private function optionsToArray(PublicKeyCredentialCreationOptions|PublicKeyCredentialRequestOptions $options): array
    {
        /** @var array<string, mixed> $encoded */
        $encoded = $this->serializer->normalize($options, 'json');

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
        $data = $this->serializer->normalize($record, 'json');

        return $data;
    }

    /**
     * @param  array<string, mixed>  $storage
     */
    private function credentialRecordFromStorage(array $storage): CredentialRecord
    {
        /** @var CredentialRecord $record */
        $record = $this->serializer->denormalize($storage, CredentialRecord::class, 'json');

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
}
