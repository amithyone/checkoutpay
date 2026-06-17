<?php

namespace App\Services\Business;

use App\Models\Business;
use App\Models\WhatsappWallet;
use App\Services\Consumer\ConsumerWalletPinVerifier;
use App\Services\Whatsapp\PhoneNormalizer;

final class BusinessWhatsappWalletLinkService
{
    public function __construct(
        private ConsumerWalletPinVerifier $pinVerifier,
    ) {}

    /**
     * @return array{ok: bool, message: string, wallet?: WhatsappWallet}
     */
    public function link(Business $business, string $phoneInput, string $pin): array
    {
        $phone = PhoneNormalizer::canonicalNgE164Digits($phoneInput);
        if ($phone === null) {
            return ['ok' => false, 'message' => 'Enter a valid Nigeria wallet phone number.'];
        }

        if (! preg_match('/^\d{4}$/', $pin)) {
            return ['ok' => false, 'message' => 'Enter the 4-digit wallet PIN.'];
        }

        $wallet = WhatsappWallet::query()
            ->where('phone_e164', $phone)
            ->where('status', WhatsappWallet::STATUS_ACTIVE)
            ->first();

        if (! $wallet) {
            return ['ok' => false, 'message' => 'No active CheckoutNow wallet found for that number.'];
        }

        if ($wallet->linked_business_id !== null && (int) $wallet->linked_business_id !== (int) $business->id) {
            return ['ok' => false, 'message' => 'That wallet is already linked to another business.'];
        }

        if (! $wallet->hasPin()) {
            return ['ok' => false, 'message' => 'That wallet has no PIN set yet. Open CheckoutNow and set a PIN first.'];
        }

        if ($wallet->isPinLocked()) {
            return ['ok' => false, 'message' => 'That wallet PIN is temporarily locked. Try again later.'];
        }

        if (! $this->pinVerifier->verify($wallet, $pin)) {
            return ['ok' => false, 'message' => 'Invalid wallet PIN.'];
        }

        $wallet->update(['linked_business_id' => $business->id]);

        return [
            'ok' => true,
            'message' => 'CheckoutNow wallet linked. Your team can use the business wallet in the app.',
            'wallet' => $wallet->fresh(),
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function unlink(Business $business): array
    {
        $updated = WhatsappWallet::query()
            ->where('linked_business_id', $business->id)
            ->update(['linked_business_id' => null]);

        if ($updated === 0) {
            return ['ok' => false, 'message' => 'No CheckoutNow wallet is linked to this business.'];
        }

        return ['ok' => true, 'message' => 'CheckoutNow wallet unlinked.'];
    }

    public function linkedWallet(Business $business): ?WhatsappWallet
    {
        return WhatsappWallet::query()
            ->where('linked_business_id', $business->id)
            ->first();
    }
}
