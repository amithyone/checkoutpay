<?php

namespace App\Services\Consumer;

use App\Models\WhatsappWallet;
use RuntimeException;

/**
 * Auto-assigns a unique pay code per wallet (WhatsApp / QR pay-in).
 * Legacy wallets keep 5-digit numeric codes; new codes are 6-char alphanumeric.
 */
final class ConsumerWalletPayCodeService
{
    private const CODE_LENGTH = 6;

    private const CODE_CHARS = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const LEGACY_LENGTH = 5;

    public function ensureForWallet(WhatsappWallet $wallet): string
    {
        $existing = strtoupper(trim((string) ($wallet->pay_code ?? '')));
        if ($existing !== '' && $this->isValidStoredCode($existing)) {
            return $existing;
        }

        $code = $this->generateUnique();
        $wallet->forceFill(['pay_code' => $code])->save();

        return $code;
    }

    public function findByPayCode(string $code): ?WhatsappWallet
    {
        $normalized = $this->normalizeInput($code);
        if ($normalized === null) {
            return null;
        }

        return WhatsappWallet::query()
            ->where('pay_code', $normalized)
            ->where('status', WhatsappWallet::STATUS_ACTIVE)
            ->first();
    }

    private function normalizeInput(string $code): ?string
    {
        $code = strtoupper(trim(preg_replace('/\s+/', '', $code) ?? ''));
        if ($code === '') {
            return null;
        }

        if (preg_match('/^\d{5}$/', $code)) {
            return $code;
        }

        if (preg_match('/^['.preg_quote(self::CODE_CHARS, '/').']{5,6}$/', $code)) {
            return $code;
        }

        $digitsOnly = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($digitsOnly) === self::LEGACY_LENGTH) {
            return $digitsOnly;
        }

        return null;
    }

    private function isValidStoredCode(string $code): bool
    {
        if (preg_match('/^\d{5}$/', $code)) {
            return true;
        }

        return (bool) preg_match('/^['.preg_quote(self::CODE_CHARS, '/').']{5,6}$/', $code);
    }

    private function generateUnique(): string
    {
        for ($attempt = 0; $attempt < 80; $attempt++) {
            $code = '';
            $max = strlen(self::CODE_CHARS) - 1;
            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= self::CODE_CHARS[random_int(0, $max)];
            }

            if (! WhatsappWallet::query()->where('pay_code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Could not allocate a unique wallet pay_code.');
    }
}
