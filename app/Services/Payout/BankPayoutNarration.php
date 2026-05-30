<?php

namespace App\Services\Payout;

use App\Models\Business;

/**
 * Resolves bank payout narration strings for MevonPay/MavonPay transfers.
 */
final class BankPayoutNarration
{
    public const WHATSAPP = 'WhatsApp wallet bank transfer';

    public const CONSUMER_APP_DEFAULT = 'Checkout App';

    public const BUSINESS_FALLBACK = 'Business withdrawal';

    public const MAX_LENGTH = 255;

    public static function forWhatsapp(): string
    {
        return self::WHATSAPP;
    }

    public static function forConsumerApp(?string $remark): string
    {
        $trimmed = self::trimAndCap($remark);

        return $trimmed !== '' ? $trimmed : self::CONSUMER_APP_DEFAULT;
    }

    public static function forBusinessWithdrawal(Business $business, ?string $bankNarration): string
    {
        $trimmed = self::trimAndCap($bankNarration);
        if ($trimmed !== '') {
            return $trimmed;
        }

        $name = trim((string) $business->name);

        return $name !== '' ? self::trimAndCap($name) : self::BUSINESS_FALLBACK;
    }

    private static function trimAndCap(?string $value): string
    {
        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return '';
        }

        return mb_substr($trimmed, 0, self::MAX_LENGTH);
    }
}
