<?php

namespace App\Services\Consumer;

use App\Models\WhatsappWalletTransaction;
use Illuminate\Database\Eloquent\Builder;

final class ConsumerWalletTransactionScope
{
    public const SCOPE_PERSONAL = 'personal';

    public const SCOPE_BUSINESS = 'business';

    public static function normalize(?string $scope): string
    {
        return strtolower(trim((string) $scope)) === self::SCOPE_BUSINESS
            ? self::SCOPE_BUSINESS
            : self::SCOPE_PERSONAL;
    }

    public static function apply(Builder $query, string $scope): Builder
    {
        $scope = self::normalize($scope);

        if ($scope === self::SCOPE_BUSINESS) {
            return $query
                ->where('ledger_scope', self::SCOPE_BUSINESS)
                ->where(function (Builder $q) {
                    $q->whereIn('type', [
                        WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT,
                        WhatsappWalletTransaction::TYPE_P2P_DEBIT,
                        WhatsappWalletTransaction::TYPE_BUSINESS_RUBIES_IN,
                    ])
                        ->orWhere(function (Builder $q2) {
                            $q2->where('amount', '>', 0)
                                ->whereNotIn('type', [
                                    WhatsappWalletTransaction::TYPE_TOPUP,
                                    WhatsappWalletTransaction::TYPE_PARTNER_MERCHANT_PAY,
                                    WhatsappWalletTransaction::TYPE_TAGINE_MERCHANT_PAY,
                                ])
                                ->where(function (Builder $q3) {
                                    $q3->whereNull('meta->payment_id')
                                        ->whereNull('meta->checkout_payment_id')
                                        ->whereNull('meta->website_payment_id');
                                });
                        });
                });
        }

        return $query->where(function (Builder $q) {
            $q->where('ledger_scope', self::SCOPE_PERSONAL)
                ->orWhereNull('ledger_scope');
        });
    }
}
