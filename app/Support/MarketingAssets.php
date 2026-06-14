<?php

namespace App\Support;

/**
 * Static marketing imagery for the public home page (from uinew design guide).
 */
final class MarketingAssets
{
    public static function url(string $key): string
    {
        $files = [
            'hero' => 'hero-payments.jpg',
            'online-payments' => 'hero-payments.jpg',
            'invoices' => 'invoices.jpg',
            'tickets' => 'tickets.jpg',
            'rentals' => 'rentals.jpg',
            'trust-card' => 'trust-card.jpg',
        ];

        $file = $files[$key] ?? $files['hero'];

        return asset('images/marketing/'.$file);
    }
}
