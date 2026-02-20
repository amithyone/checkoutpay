<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\URL;

class PwaController extends Controller
{
    /**
     * Serve the web app manifest for PWA (Android and desktop).
     */
    public function manifest(): JsonResponse
    {
        try {
            $name = Setting::get('site_name', config('app.name', 'CheckoutPay'));
            $shortName = strlen($name) > 12 ? substr($name, 0, 12) . '…' : $name;
            // Use Landing Pages Logo first for PWA icon, then favicon (PNG/JPG/SVG all supported)
            $logo = Setting::get('site_logo') ?: Setting::get('site_favicon');
            $baseUrl = rtrim(config('app.url'), '/');

            $iconSrc = $logo ? URL::asset('storage/' . $logo) : $baseUrl . '/images/pwa/icon-192.png';
            $iconType = self::mimeForPath($logo);

            $manifest = [
                'name' => $name,
                'short_name' => $shortName,
                'description' => 'Intelligent Payment Gateway',
                'start_url' => URL::route('business.login'),
                'scope' => '/',
                'display' => 'standalone',
                'orientation' => 'portrait-primary',
                'theme_color' => '#3C50E0',
                'background_color' => '#ffffff',
                'icons' => [
                    [
                        'src' => $iconSrc,
                        'sizes' => '192x192',
                        'type' => $iconType,
                        'purpose' => 'any',
                    ],
                    [
                        'src' => $logo ? URL::asset('storage/' . $logo) : $baseUrl . '/images/pwa/icon-512.png',
                        'sizes' => '512x512',
                        'type' => $iconType,
                        'purpose' => 'any maskable',
                    ],
                ],
                'categories' => ['finance', 'business'],
            ];

            return response()->json($manifest)
                ->header('Cache-Control', 'public, max-age=3600')
                ->header('Content-Type', 'application/manifest+json');
        } catch (\Throwable $e) {
            return response()->json([
                'name' => config('app.name', 'CheckoutPay'),
                'short_name' => 'CheckoutPay',
                'start_url' => URL::to('/dashboard/login'),
                'display' => 'standalone',
                'theme_color' => '#3C50E0',
                'background_color' => '#ffffff',
                'icons' => [
                    ['src' => URL::asset('images/pwa/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                    ['src' => URL::asset('images/pwa/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ],
            ])->header('Cache-Control', 'public, max-age=3600')
                ->header('Content-Type', 'application/manifest+json');
        }
    }

    /** MIME type for manifest icon (PNG, JPG, SVG all valid for PWA). */
    private static function mimeForPath(?string $path): string
    {
        if (!$path) {
            return 'image/png';
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'svg' => 'image/svg+xml',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'image/png',
        };
    }
}
