<?php

namespace App\Http\Controllers\Concerns;

trait HandlesWhatsappWalletWebPinSubmit
{
    protected function isConsumedWhatsappWalletWebLinkError(string $message): bool
    {
        $m = strtolower($message);

        return str_contains($m, 'expired')
            || str_contains($m, 'already used')
            || str_contains($m, 'already completed')
            || str_contains($m, 'already set')
            || str_contains($m, 'invalid or has expired');
    }
}
