<?php

namespace App\Services;

use App\Models\RentalDeviceToken;
use Google\Auth\Credentials\ServiceAccountCredentials;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
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

        $this->sendToTokens($tokens, $title, $body, $data, 'rentals_alerts');
    }

    /**
     * @return list<string> tokens that FCM rejected as invalid/unregistered
     */
    public function sendToTokens(
        array $tokens,
        string $title,
        string $body,
        array $data = [],
        string $androidChannelId = 'rentals_alerts',
    ): array {
        if (empty($tokens)) {
            return [];
        }

        $projectId = (string) config('services.firebase.project_id', '');
        $serviceAccount = (string) config('services.firebase.service_account_json', '');
        if ($projectId === '' || $serviceAccount === '') {
            return [];
        }

        $accessToken = $this->getAccessToken($serviceAccount);
        if (! $accessToken) {
            return [];
        }

        $endpoint = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $client = new Client(['timeout' => 10]);
        $failedTokens = [];

        foreach ($tokens as $token) {
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
                if ($response->getStatusCode() >= 400) {
                    $failedTokens[] = (string) $token;
                }
            } catch (\Throwable $e) {
                Log::warning('FCM push send failed', [
                    'token_suffix' => substr((string) $token, -12),
                    'error' => $e->getMessage(),
                ]);
                if ($this->isInvalidTokenError($e)) {
                    $failedTokens[] = (string) $token;
                }
            }
        }

        return $failedTokens;
    }

    private function isInvalidTokenError(\Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());

        return str_contains($msg, 'notregistered')
            || str_contains($msg, 'invalidregistration')
            || str_contains($msg, 'unregistered')
            || str_contains($msg, 'not found')
            || str_contains($msg, 'requested entity was not found');
    }

    public function isConfigured(): bool
    {
        $projectId = (string) config('services.firebase.project_id', '');
        $serviceAccount = (string) config('services.firebase.service_account_json', '');
        return $projectId !== '' && $serviceAccount !== '';
    }

    private function getAccessToken(string $serviceAccountValue): ?string
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
            if (! is_array($decoded)) {
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

