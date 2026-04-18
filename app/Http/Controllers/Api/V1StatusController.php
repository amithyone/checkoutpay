<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class V1StatusController extends Controller
{
    /**
     * Lightweight JSON for GET /api/v1 — confirms API v1 is reachable and whether
     * the public webhook base URL (WHATSAPP_APP_URL or APP_URL) is HTTPS and non-local.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $base = rtrim((string) config('whatsapp.public_url', ''), '/');

        return response()->json([
            'success' => true,
            'api_version' => 'v1',
            'webhook_base_url' => $base !== '' ? $base : null,
            'webhook_base_url_active' => $this->isWebhookBaseUrlActive($base),
        ]);
    }

    protected function isWebhookBaseUrlActive(string $base): bool
    {
        if ($base === '') {
            return false;
        }

        $parts = parse_url($base);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($scheme !== 'https') {
            return false;
        }

        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return false;
        }

        return true;
    }
}
