<?php

namespace App\Support;

final class Seo
{
    /**
     * @param  array{title?: string, description?: string, path?: string, image?: string, keywords?: string, noindex?: bool}  $overrides
     * @return array{title: string, description: string, keywords: string, canonical: string, image: string, noindex: bool}
     */
    /**
     * @return array{title: string, description: string, keywords: string, canonical: string, image: string, noindex: bool}
     */
    public static function forPath(string $path): array
    {
        $path = self::normalizePublicPath($path);
        $pages = config('seo_pages', []);
        $overrides = is_array($pages[$path] ?? null) ? $pages[$path] : [];
        $overrides['path'] = $path;

        return self::resolve($overrides);
    }

    public static function resolve(array $overrides = []): array
    {
        $base = rtrim((string) config('app.url'), '/');
        $path = self::normalizePublicPath((string) ($overrides['canonical_path'] ?? $overrides['path'] ?? '/'));

        $pages = config('seo_pages', []);
        $pageMeta = is_array($pages[$path] ?? null) ? $pages[$path] : [];

        $image = $overrides['image'] ?? (string) config('seo.og_image', '/checkout-logo.png');
        if ($image !== '' && ! str_starts_with($image, 'http')) {
            $image = $base.'/'.ltrim($image, '/');
        }

        return [
            'title' => (string) ($overrides['title'] ?? $pageMeta['title'] ?? config('seo.default_title')),
            'description' => (string) ($overrides['description'] ?? $pageMeta['description'] ?? config('seo.default_description')),
            'keywords' => (string) ($overrides['keywords'] ?? $pageMeta['keywords'] ?? config('seo.default_keywords')),
            'canonical' => self::absoluteCanonicalUrl($path),
            'image' => $image,
            'noindex' => (bool) ($overrides['noindex'] ?? $pageMeta['noindex'] ?? false),
        ];
    }

    /**
     * Preferred public path for a CMS page slug (top-level vs /page/{slug}).
     */
    public static function publicPathForPageSlug(string $slug): string
    {
        $slug = trim($slug, '/');
        if ($slug === '') {
            return '/';
        }

        if (in_array($slug, self::dedicatedPageSlugs(), true)) {
            return '/'.$slug;
        }

        return '/page/'.$slug;
    }

    /**
     * @return list<string>
     */
    public static function dedicatedPageSlugs(): array
    {
        $slugs = config('seo.dedicated_page_slugs', []);

        return is_array($slugs) ? array_values($slugs) : [];
    }

    public static function normalizePublicPath(string $path): string
    {
        $path = '/'.trim($path, '/');
        if ($path === '//') {
            return '/';
        }

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return $path === '' ? '/' : $path;
    }

    public static function absoluteCanonicalUrl(string $path): string
    {
        $base = rtrim((string) config('app.url'), '/');

        return $base.self::normalizePublicPath($path);
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

    /**
     * @param  array<int, array{q: string, a: string}>  $items
     * @return array<string, mixed>
     */
    public static function faqPageJsonLd(array $items): array
    {
        $mainEntity = [];
        foreach ($items as $item) {
            $q = trim((string) ($item['q'] ?? $item['question'] ?? ''));
            $a = trim(strip_tags((string) ($item['a'] ?? $item['answer'] ?? '')));
            if ($q === '' || $a === '') {
                continue;
            }
            $mainEntity[] = [
                '@type' => 'Question',
                'name' => $q,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $a,
                ],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $mainEntity,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function softwareApplicationJsonLd(): array
    {
        $base = rtrim((string) config('app.url'), '/');
        $version = (string) config('checkout.wordpress_plugin.version', '1.0.0');

        return [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => 'COPN Payment Gateway for Nigerian Businesses (CheckoutPay WooCommerce Plugin)',
            'applicationCategory' => 'PaymentApplication',
            'operatingSystem' => 'WordPress',
            'softwareVersion' => $version,
            'description' => 'Official WooCommerce payment gateway plugin for Nigerian bank transfer and virtual account checkout.',
            'url' => $base.'/wordpress-plugin',
            'downloadUrl' => $base.'/wordpress-plugin',
            'offers' => [
                '@type' => 'Offer',
                'price' => '0',
                'priceCurrency' => 'NGN',
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => (string) config('seo.site_name', 'CheckoutPay'),
            ],
        ];
    }

    /**
     * @param  array<int, array{name: string, url: string}>  $crumbs
     * @return array<string, mixed>
     */
    public static function breadcrumbJsonLd(array $crumbs): array
    {
        $items = [];
        foreach ($crumbs as $i => $crumb) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }
}
