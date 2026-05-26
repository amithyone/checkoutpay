<?php

namespace App\Support;

final class FaqCatalog
{
    /**
     * @return array<string, array{label: string, slug: string, pages: array<int, string>}>
     */
    public static function categories(): array
    {
        return config('faqs.categories', []);
    }

    /**
     * @return array<int, array{category: string, q: string, a: string, keywords?: array<int, string>, html?: bool}>
     */
    public static function all(): array
    {
        return config('faqs.items', []);
    }

    /**
     * @return array<int, array{category: string, q: string, a: string, keywords?: array<int, string>, html?: bool}>
     */
    public static function forCategory(string $slug): array
    {
        return array_values(array_filter(
            self::all(),
            fn (array $item) => ($item['category'] ?? '') === $slug
        ));
    }

    /**
     * @return array<int, array{category: string, q: string, a: string, keywords?: array<int, string>, html?: bool}>
     */
    public static function forPage(string $path): array
    {
        $path = '/'.ltrim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/') ?: '/';
        }

        $slugs = [];
        foreach (self::categories() as $slug => $meta) {
            $pages = $meta['pages'] ?? [];
            if (in_array($path, $pages, true)) {
                $slugs[] = $slug;
            }
        }

        if ($slugs === []) {
            return [];
        }

        return array_values(array_filter(
            self::all(),
            fn (array $item) => in_array($item['category'] ?? '', $slugs, true)
        ));
    }

    /**
     * @return array<int, array{category: string, q: string, a: string, keywords?: array<int, string>, html?: bool}>
     */
    public static function search(?string $query): array
    {
        $query = trim((string) $query);
        if ($query === '') {
            return self::all();
        }

        $needle = mb_strtolower($query);

        return array_values(array_filter(self::all(), function (array $item) use ($needle) {
            $haystack = mb_strtolower(
                ($item['q'] ?? '').' '.
                ($item['a'] ?? '').' '.
                implode(' ', $item['keywords'] ?? [])
            );

            return str_contains($haystack, $needle);
        }));
    }

    /**
     * @param  array<int, array{category: string, q: string, a: string}>  $items
     * @return array<string, mixed>
     */
    public static function faqPageJsonLd(array $items): array
    {
        return Seo::faqPageJsonLd($items);
    }

    public static function formatAnswer(array $item): string
    {
        $text = (string) ($item['a'] ?? '');

        $replacements = [
            '{apply_url}' => route('developers.program.apply'),
            '{program_url}' => route('developers.program'),
            '{developers_url}' => route('developers.index'),
            '{api_docs_url}' => route('api-docs'),
            '{wordpress_url}' => route('wordpress-plugin.index'),
            '{support_url}' => route('support.index'),
            '{contact_url}' => route('contact'),
            '{pricing_url}' => route('pricing'),
            '{register_url}' => route('business.register'),
            '{site_name}' => (string) config('seo.site_name', 'CheckoutPay'),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }
}
