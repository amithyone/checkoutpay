<?php

namespace App\Services\Rentals;

use App\Models\Rental;

/**
 * Typed push `data` payloads for CheckoutNow / rentals native deep links.
 *
 * @see mobile contract: type, role, rentalId, href (+ snake aliases)
 */
final class RentalsPushContract
{
    public const TYPE_RENTAL_REQUEST_NEW = 'rental_request_new';

    public const TYPE_PICKUP_REQUEST = 'pickup_request';

    public const TYPE_RENTAL_APPROVED = 'rental_approved';

    public const TYPE_RENTAL_DENIED = 'rental_denied';

    public const TYPE_RENTAL_CANCELLED = 'rental_cancelled';

    public const TYPE_RENTAL_ACTIVE = 'rental_active';

    public const TYPE_RENTAL_DELIVERED = 'rental_delivered';

    public const TYPE_RETURN_DUE_SOON = 'return_due_soon';

    public const TYPE_RETURN_OVERDUE = 'return_overdue';

    public const TYPE_WALLET_CREDIT = 'wallet_credit';

    public const TYPE_WALLET_DEBIT = 'wallet_debit';

    public const TYPE_SUPPORT_MESSAGE = 'support_message';

    public const TYPE_DISPUTE_UPDATE = 'dispute_update';

    public const TYPE_GENERIC = 'generic';

    public const ROLE_RENTER = 'renter';

    public const ROLE_BUSINESS = 'business';

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, string>
     */
    public static function rentalPayload(
        string $type,
        string $role,
        Rental $rental,
        ?string $href = null,
        array $extra = []
    ): array {
        $id = (int) $rental->id;
        $number = (string) ($rental->rental_number ?? '');
        $resolvedHref = $href ?? self::defaultRentalHref($type, $role, $id);

        return self::stringify(array_merge([
            'type' => $type,
            'role' => $role,
            'rentalId' => $id,
            'rental_id' => $id,
            'rentalNumber' => $number,
            'rental_number' => $number,
            'href' => $resolvedHref,
        ], $extra));
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, string>
     */
    public static function walletPayload(string $type, string $role, float $amount, array $extra = []): array
    {
        $href = $role === self::ROLE_BUSINESS ? '/manage/wallet' : '/wallet';

        return self::stringify(array_merge([
            'type' => $type,
            'role' => $role,
            'amount' => $amount,
            'href' => $href,
        ], $extra));
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, string>
     */
    public static function supportPayload(string $role, int $ticketId, array $extra = []): array
    {
        return self::stringify(array_merge([
            'type' => self::TYPE_SUPPORT_MESSAGE,
            'role' => $role,
            'ticketId' => $ticketId,
            'ticket_id' => $ticketId,
            'href' => '/notifications',
        ], $extra));
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, string>
     */
    public static function genericPayload(string $role, string $href = '/notifications', array $extra = []): array
    {
        return self::stringify(array_merge([
            'type' => self::TYPE_GENERIC,
            'role' => $role,
            'href' => $href,
        ], $extra));
    }

    public static function defaultRentalHref(string $type, string $role, int $rentalId): string
    {
        if ($type === self::TYPE_RENTAL_REQUEST_NEW && $role === self::ROLE_BUSINESS) {
            return $rentalId > 0 ? '/manage/orders/'.$rentalId : '/manage/orders';
        }

        if ($role === self::ROLE_BUSINESS) {
            return $rentalId > 0 ? '/manage/orders/'.$rentalId : '/manage/orders';
        }

        return $rentalId > 0 ? '/order/'.$rentalId : '/notifications';
    }

    /**
     * FCM / Expo / APNs data maps must be string values.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public static function stringify(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }
            if (is_bool($value)) {
                $out[(string) $key] = $value ? '1' : '0';
            } elseif (is_array($value)) {
                $out[(string) $key] = json_encode($value) ?: '';
            } else {
                $out[(string) $key] = (string) $value;
            }
        }

        return $out;
    }
}
