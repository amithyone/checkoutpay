<?php

namespace App\Exceptions;

use RuntimeException;

final class WebAuthnNotConfiguredException extends RuntimeException
{
    public static function missingPackages(): self
    {
        return new self(
            'Passkeys are not configured on this server. Install web-auth/webauthn-lib and web-auth/cose-lib, then run composer install.'
        );
    }
}
