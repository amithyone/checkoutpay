<?php

namespace App\Services\Consumer;

use App\Models\WhatsappWallet;
use RuntimeException;

/**
 * Auto-assigns a unique 5-digit pay code per wallet (WhatsApp / QR pay-in).
 */
final class ConsumerWalletPayCodeService
{
    public function ensureForWallet(WhatsappWallet $wallet): string
    {
        $existing = trim((string) ($wallet->pay_code ?? ''));
        if ($existing !== '' && strlen($existing) === 5) {
            return $existing;
        }

        $code = $this->generateUnique();
        $wallet->forceFill(['pay_code' => $code])->save();

        return $code;
    }

    public function findByPayCode(string $code): ?WhatsappWallet
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== 5) {
            return null;
        }

        return WhatsappWallet::query()
            ->where('pay_code', $code)
            ->where('status', WhatsappWallet::STATUS_ACTIVE)
            ->first();
    }

    private function generateUnique(): string
    {
        for ($attempt = 0; $attempt < 80; $attempt++) {
            $code = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
            if (! WhatsappWallet::query()->where('pay_code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Could not allocate a unique wallet pay_code.');
    }
}
