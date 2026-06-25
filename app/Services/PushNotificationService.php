<?php

namespace App\Services;

use App\Models\RentalDeviceToken;
use App\Services\Push\ApnsPushNotificationService;
use App\Services\Push\PushTokenDeliveryClassifier;
use Google\Auth\Credentials\ServiceAccountCredentials;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    public const PROFILE_RENTALS = 'rentals';

    public const PROFILE_CHECKOUTNOW = 'checkoutnow';

    public function __construct(
        private ApnsPushNotificationService $apns,
    ) {}

    public function notifyRenter(
        int $renterId,
        string $title,
        string $body,
        array $data = []
    ): void {
        $tokens = RentalDeviceToken::query()
            ->where('renter_id', $renterId)
            ->where('platform', '!=', 'web')
            ->pluck('token')
            ->all();

        $this->sendToTokens($tokens, $title, $body, $data);
    }

    public function notifyBusiness(
        int $businessId,
        string $title,
        string $body,
        array $data = []
    ): void {
        $tokens = RentalDeviceToken::query()
            ->where('business_id', $businessId)
            ->where('platform', '!=', 'web')
            ->pluck('token')
            ->all();

        $this->sendToTokens($tokens, $title, $body, $data, 'rentals_alerts', self::PROFILE_RENTALS);
    }

    /**
     * @param  array<int, string|array{token: string, platform?: ?string}>  $tokens
     * @return list<string> tokens rejected as invalid/unregistered
     */
    public function sendToTokens(
        array $tokens,
        string $title,
        string $body,
        array $data = [],
        string $androidChannelId = 'rentals_alerts',
        string $profile = self::PROFILE_RENTALS,
    ): array {
        if (empty($tokens)) {
            return [];
        }

        $failedTokens = [];
        foreach ($this->normalizeTokenTargets($tokens) as $target) {
            $token = $target['token'];
            if ($this->shouldDeliverViaApns($target['platform'], $token, $profile)) {
                $failedTokens = array_merge(
                    $failedTokens,
                    $this->apns->sendToDevice($token, $title, $body, $data, $profile),
                );
                continue;
            }

            $failedTokens = array_merge(
                $failedTokens,
                $this->sendSingleFcmToken($token, $title, $body, $data, $androidChannelId, $profile),
            );
        }

        return array_values(array_unique(array_filter($failedTokens)));
    }

    /**
     * @param  array<int, string|array{token: string, platform?: ?string}>  $tokens
     * @return list<array{token: string, platform: ?string}>
     */
    private function normalizeTokenTargets(array $tokens): array
    {
        $targets = [];
        foreach ($tokens as $item) {
            if (is_string($item)) {
                $targets[] = ['token' => $item, 'platform' => null];
                continue;
            }

            if (is_array($item) && isset($item['token'])) {
                $targets[] = [
                    'token' => (string) $item['token'],
                    'platform' => isset($item['platform']) ? (string) $item['platform'] : null,
                ];
            }
        }

        return $targets;
    }

    private function shouldDeliverViaApns(?string $platform, string $token, string $profile): bool
    {
        if ($profile !== self::PROFILE_CHECKOUTNOW || ! $this->apns->isConfigured($profile)) {
            return false;
        }

        return PushTokenDeliveryClassifier::shouldDeliverViaApns($platform, $token);
    }

    /**
     * @return list<string>
     */
    private function sendSingleFcmToken(
        string $token,
        string $title,
        string $body,
        array $data,
        string $androidChannelId,
        string $profile,
    ): array {
        [$projectId, $serviceAccount] = $this->resolveCredentials($profile);
        if ($projectId === '' || $serviceAccount === '') {
            return [];
        }

        $accessToken = $this->getAccessToken($serviceAccount);
        if (! $accessToken) {
            return [];
        }

        $endpoint = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $client = new Client(['timeout' => 10]);

        $message = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $this->normalizeData($data),
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'sound' => 'default',
                        'channel_id' => $androidChannelId,
                    ],
                ],
                'apns' => [
                    'headers' => [
                        'apns-priority' => '10',
                    ],
                    'payload' => [
                        'aps' => [
                            'alert' => [
                                'title' => $title,
                                'body' => $body,
                            ],
                            'sound' => 'default',
                        ],
                    ],
                ],
            ],
        ];

        try {
            $response = $client->post($endpoint, [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $message,
            ]);
            $status = $response->getStatusCode();
            if ($status >= 400) {
                Log::warning('FCM push rejected', [
                    'status' => $status,
                    'profile' => $profile,
                    'project_id' => $projectId,
                    'token_suffix' => substr($token, -12),
                    'body' => substr((string) $response->getBody(), 0, 500),
                ]);

                return [$token];
            }

            $responseBody = json_decode((string) $response->getBody(), true);
            Log::info('FCM push accepted', [
                'profile' => $profile,
                'project_id' => $projectId,
                'fcm_message' => is_array($responseBody) ? ($responseBody['name'] ?? null) : null,
                'token_suffix' => substr($token, -12),
                'type' => $data['type'] ?? null,
            ]);

            return [];
        } catch (\Throwable $e) {
            Log::warning('FCM push send failed', [
                'profile' => $profile,
                'token_suffix' => substr($token, -12),
                'error' => $e->getMessage(),
            ]);
            if ($this->isInvalidTokenError($e)) {
                return [$token];
            }

            return [];
        }
    }

    private function isInvalidTokenError(\Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());

        return str_contains($msg, 'notregistered')
            || str_contains($msg, 'invalidregistration')
            || str_contains($msg, 'unregistered')
            || str_contains($msg, 'not a valid fcm registration token')
            || str_contains($msg, 'invalid registration token')
            || str_contains($msg, 'invalid_argument')
            || str_contains($msg, 'not found')
            || str_contains($msg, 'requested entity was not found');
    }

    public function isConfigured(string $profile = self::PROFILE_RENTALS): bool
    {
        if ($profile === self::PROFILE_CHECKOUTNOW && $this->apns->isConfigured($profile)) {
            return true;
        }

        return $this->isFcmConfigured($profile);
    }

    public function isFcmConfigured(string $profile = self::PROFILE_RENTALS): bool
    {
        [$projectId, $serviceAccount] = $this->resolveCredentials($profile);

        if ($projectId === '' || $serviceAccount === '') {
            return false;
        }

        return $this->resolveServiceAccountJson($serviceAccount) !== null;
    }

    public function isApnsConfigured(string $profile = self::PROFILE_CHECKOUTNOW): bool
    {
        return $this->apns->isConfigured($profile);
    }

    private function resolveServiceAccountJson(string $serviceAccountValue): ?array
    {
        try {
            $json = $serviceAccountValue;
            $path = $serviceAccountValue;
            if ($path !== '' && ! is_file($path)) {
                $isAbsoluteUnix = str_starts_with($path, '/');
                $isAbsoluteWindows = strlen($path) > 2 && ctype_alpha($path[0]) && $path[1] === ':' && ($path[2] === '\\' || $path[2] === '/');
                if (! $isAbsoluteUnix && ! $isAbsoluteWindows) {
                    $candidate = base_path($path);
                    if (is_file($candidate)) {
                        $path = $candidate;
                    }
                }
            }
            if ($path !== '' && is_file($path)) {
                $json = (string) file_get_contents($path);
            }
            $decoded = json_decode($json, true);

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveCredentials(string $profile): array
    {
        $projectId = (string) config("services.firebase.{$profile}.project_id", '');
        $serviceAccount = (string) config("services.firebase.{$profile}.service_account_json", '');

        return [$projectId, $serviceAccount];
    }

    private function getAccessToken(string $serviceAccountValue): ?string
    {
        try {
            $decoded = $this->resolveServiceAccountJson($serviceAccountValue);
            if ($decoded === null) {
                return null;
            }

            $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];
            $credentials = new ServiceAccountCredentials($scopes, $decoded);
            $auth = $credentials->fetchAuthToken();

            return $auth['access_token'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('FCM auth token fetch failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function normalizeData(array $data): array
    {
        $normalized = [];
        foreach ($data as $key => $value) {
            $normalized[(string) $key] = is_scalar($value) ? (string) $value : json_encode($value);
        }

        return $normalized;
    }
}
