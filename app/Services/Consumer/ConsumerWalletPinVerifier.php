<?php

namespace App\Services\Consumer;

use App\Models\WhatsappWallet;
use Illuminate\Support\Facades\Hash;

class ConsumerWalletPinVerifier
{
    public function verify(WhatsappWallet $wallet, string $pin): bool
    {
        if (! $wallet->hasPin()) {
            return false;
        }

        return Hash::check($pin, (string) $wallet->pin_hash);
    }
}
