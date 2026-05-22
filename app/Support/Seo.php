<?php

namespace App\Support;

final class Seo
{
    /**
     * @param  array{title?: string, description?: string, path?: string, image?: string, keywords?: string, noindex?: bool}  $overrides
     * @return array{title: string, description: string, keywords: string, canonical: string, image: string, noindex: bool}
     */
    public static function resolve(array $overrides = []): array
    {
        $base = rtrim((string) config('app.url'), '/');
        $path = $overrides['path'] ?? '/';
        if ($path !== '/' && ! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        $image = $overrides['image'] ?? (string) config('seo.og_image', '/checkout-logo.png');
        if ($image !== '' && ! str_starts_with($image, 'http')) {
            $image = $base.'/'.ltrim($image, '/');
        }

        return [
            'title' => (string) ($overrides['title'] ?? config('seo.default_title')),
            'description' => (string) ($overrides['description'] ?? config('seo.default_description')),
            'keywords' => (string) ($overrides['keywords'] ?? config('seo.default_keywords')),
            'canonical' => $base.$path,
            'image' => $image,
            'noindex' => (bool) ($overrides['noindex'] ?? false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function organizationJsonLd(): array
    {
        $base = rtrim((string) config('app.url'), '/');
        $org = config('seo.organization', []);

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FinancialService',
            'name' => (string) config('seo.site_name', 'CheckoutPay'),
            'alternateName' => ['Checkout Pay', 'check-outpay.com', 'CheckoutNow'],
            'url' => $org['url'] ?? $base,
            'logo' => $base.'/'.ltrim((string) ($org['logo'] ?? '/checkout-logo.png'), '/'),
            'description' => (string) config('seo.default_description'),
            'areaServed' => [
                '@type' => 'Country',
                'name' => (string) ($org['area_served'] ?? 'Nigeria'),
            ],
            'priceRange' => (string) ($org['price_range'] ?? '₦'),
            'knowsAbout' => [
                'Payment gateway',
                'Bank transfer reconciliation',
                'Virtual account payments',
                'WooCommerce payments',
                'WhatsApp wallet',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function websiteJsonLd(): array
    {
        $base = rtrim((string) config('app.url'), '/');

        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => (string) config('seo.site_name', 'CheckoutPay'),
            'url' => $base,
            'description' => (string) config('seo.default_description'),
            'inLanguage' => 'en-NG',
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => $base.'/faqs?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }
}
