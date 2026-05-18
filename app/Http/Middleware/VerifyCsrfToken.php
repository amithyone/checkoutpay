<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'api/*',
        'setup/*', // Allow setup routes without CSRF during installation
        // One-time token in URL; session cookies are unreliable in WhatsApp in-app browsers.
        'wallet/whatsapp/confirm/*',
        'wallet/whatsapp/set-pin/*',
        'wallet/whatsapp/vtu-confirm/*',
        'wallet/partner-pay/*',
    ];
}
