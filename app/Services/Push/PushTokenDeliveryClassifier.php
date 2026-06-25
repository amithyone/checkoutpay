<?php

namespace App\Services\Push;

/**
 * Distinguishes APNs device tokens (hex) from FCM registration tokens.
 */
final class PushTokenDeliveryClassifier
{
    public static function looksLikeApnsDeviceToken(string $token): bool
    {
        $normalized = strtolower(preg_replace('/[<>\s]/', '', $token) ?? '');

        return $normalized !== ''
            && strlen($normalized) >= 32
            && strlen($normalized) <= 200
            && (bool) preg_match('/^[0-9a-f]+$/', $normalized);
    }

    public static function looksLikeFcmRegistrationToken(string $token): bool
    {
        $trimmed = trim($token);

        return $trimmed !== ''
            && (str_contains($trimmed, ':') || strlen($trimmed) > 80)
            && ! self::looksLikeApnsDeviceToken($trimmed);
    }

    public static function shouldDeliverViaApns(?string $platform, string $token): bool
    {
        $platform = strtolower(trim((string) $platform));

        if ($platform === 'ios') {
            return true;
        }

        if ($platform === 'android') {
            return false;
        }

        return self::looksLikeApnsDeviceToken($token);
    }
}
