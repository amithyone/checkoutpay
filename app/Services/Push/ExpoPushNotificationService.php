<?php

namespace App\Services\Push;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * Expo Push API for ExponentPushToken[…] / ExpoPushToken[…] device tokens.
 *
 * @see https://docs.expo.dev/push-notifications/sending-notifications/
 */
final class ExpoPushNotificationService
{
    public const ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    public static function isExpoPushToken(string $token): bool
    {
        $t = trim($token);

        return str_starts_with($t, 'ExponentPushToken[')
            || str_starts_with($t, 'ExpoPushToken[');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string> failed tokens
     */
    public function sendToDevice(
        string $token,
        string $title,
        string $body,
        array $data = [],
        string $channelId = 'rentals',
    ): array {
        if (! self::isExpoPushToken($token)) {
            return [];
        }

        $payload = [
            'to' => $token,
            'title' => $title,
            'body' => $body,
            'sound' => 'default',
            'channelId' => $channelId,
            'data' => $this->normalizeData($data),
        ];

        try {
            $client = new Client(['timeout' => 12]);
            $response = $client->post(self::ENDPOINT, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Accept-Encoding' => 'gzip, deflate',
                ],
                'json' => $payload,
            ]);
            $json = json_decode((string) $response->getBody(), true);
            $status = $json['data']['status'] ?? ($json['data'][0]['status'] ?? null);

            // Expo may return { data: { status: "ok"|"error" } } or batch array
            if (is_array($json['data'] ?? null) && array_is_list($json['data'])) {
                $ticket = $json['data'][0] ?? [];
                $status = $ticket['status'] ?? null;
                if ($status === 'error') {
                    Log::warning('expo.push.error', [
                        'message' => $ticket['message'] ?? null,
                        'details' => $ticket['details'] ?? null,
                        'token_suffix' => substr($token, -16),
                    ]);

                    return [$token];
                }
            } elseif ($status === 'error') {
                Log::warning('expo.push.error', [
                    'body' => substr((string) $response->getBody(), 0, 500),
                    'token_suffix' => substr($token, -16),
                ]);

                return [$token];
            }

            Log::info('expo.push.accepted', [
                'token_suffix' => substr($token, -16),
                'type' => $data['type'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('expo.push.failed', [
                'error' => $e->getMessage(),
                'token_suffix' => substr($token, -16),
            ]);

            return [$token];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeData(array $data): array
    {
        // Expo accepts non-string values; keep primitives for client routing.
        $out = [];
        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }
            if (is_numeric($value) && ! is_string($value)) {
                $out[$key] = $value + 0;
            } elseif (is_bool($value)) {
                $out[$key] = $value;
            } else {
                $out[$key] = is_scalar($value) ? $value : json_encode($value);
            }
        }

        return $out;
    }
}
