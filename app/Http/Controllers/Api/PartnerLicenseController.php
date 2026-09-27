<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Partner\PartnerLicenseIssuerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerLicenseController extends Controller
{
    public function __construct(
        private PartnerLicenseIssuerService $issuer,
    ) {}

    public function ping(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'license_key' => 'required|string|max:80',
            'hostname' => 'nullable|string|max:255',
            'build_version' => 'nullable|string|max:32',
            'build_id' => 'nullable|string|max:64',
            'php_version' => 'nullable|string|max:32',
            'product' => 'nullable|string|max:64',
        ]);

        $result = $this->issuer->handlePing(
            (string) $validated['license_key'],
            $validated,
        );

        $status = ($result['ok'] ?? false) ? 200 : 403;

        return response()->json($result, $status);
    }
}
