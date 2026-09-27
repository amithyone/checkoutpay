<?php

namespace App\Http\Middleware;

use App\Services\Partner\PartnerLicenseClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePartnerLicenseValid
{
    public function __construct(
        private PartnerLicenseClient $license,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->license->isEnforced()) {
            return $next($request);
        }

        if ($request->is('api/v1/partner-license/*')) {
            return $next($request);
        }

        if (! $this->license->shouldBlockLicensedFeatures()) {
            return $next($request);
        }

        $message = $this->license->blockMessage();

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'code' => 'partner_license_invalid',
            ], 503);
        }

        abort(503, $message);
    }
}
