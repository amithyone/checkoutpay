<?php

namespace Database\Seeders;

/**
 * HTML content for public Legal & Security pages (CheckoutPay).
 * Body copy lives in database/legal/*.html — high-level only, no trade secrets.
 */
final class LegalPagesDefinitions
{
    public const LAST_UPDATED = 'March 2026';

    /** @var array<string, string> */
    private const FILE_SLUGS = [
        'terms-and-conditions' => 'terms-and-conditions.html',
        'privacy-policy' => 'privacy-policy.html',
        'security' => 'security.html',
        'fraud-awareness' => 'fraud-awareness.html',
    ];

    /**
     * @return array<string, array{title: string, content: string, meta_title: string, meta_description: string, order: int}>
     */
    public static function all(): array
    {
        $u = self::LAST_UPDATED;

        return [
            'security' => [
                'title' => 'Security',
                'meta_title' => 'Security - CheckoutPay',
                'meta_description' => 'How CheckoutPay protects accounts, payments, and business data.',
                'order' => 10,
                'content' => self::contentFor('security', $u),
            ],
            'privacy-policy' => [
                'title' => 'Privacy Policy',
                'meta_title' => 'Privacy Policy - CheckoutPay',
                'meta_description' => 'How CheckoutPay handles personal and business information.',
                'order' => 11,
                'content' => self::contentFor('privacy-policy', $u),
            ],
            'terms-and-conditions' => [
                'title' => 'Terms and Conditions',
                'meta_title' => 'Terms and Conditions - CheckoutPay',
                'meta_description' => 'Terms governing use of CheckoutPay payment gateway and related services in Nigeria.',
                'order' => 12,
                'content' => self::contentFor('terms-and-conditions', $u),
            ],
            'esg-policy' => [
                'title' => 'ESG Policy',
                'meta_title' => 'ESG Policy - CheckoutPay',
                'meta_description' => 'Environmental, social, and governance commitments for CheckoutPay.',
                'order' => 13,
                'content' => self::wrap(<<<'HTML'
<p class="text-gray-600 text-sm mb-6"><strong>Last updated:</strong> LAST_UPDATED_PLACEHOLDER</p>
<p>CheckoutPay recognises that environmental, social, and governance (“ESG”) factors matter to customers, partners, and regulators. This policy sets out our commitments at a high level. It is not a sustainability report with quantitative metrics; those may be published separately where appropriate.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Environment</h2>
<p>We favour efficient infrastructure and responsible use of computing resources to limit unnecessary energy consumption. We encourage digital delivery of receipts and records to reduce paper waste. We comply with applicable environmental laws in the jurisdictions where we operate.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Social</h2>
<p>Our mission includes expanding safe access to payment tools for legitimate Nigerian businesses. We support financial integrity through fraud awareness, clear communications, and cooperation with lawful regulatory requests. We treat customers and employees with respect and do not tolerate discrimination or harassment.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Governance</h2>
<p>We maintain internal controls appropriate to a financial technology platform, including segregation of duties where practicable, approval processes for sensitive changes, and documented policies for security and compliance. Payment safeguarding and regulatory alignment are supported through our relationship with <strong>MetroOven Innovations</strong> under applicable <strong>CBN</strong> and <strong>NDIC</strong> frameworks as relevant to its licence.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Reporting concerns</h2>
<p>Ethics or compliance concerns may be raised through official support or whistle-blowing channels we publish. Retaliation against good-faith reporters is prohibited.</p>

<h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">Review</h2>
<p>We review this policy periodically and update the “Last updated” date when we make substantive changes.</p>
HTML
                    , $u),
            ],
            'fraud-awareness' => [
                'title' => 'Fraud Awareness',
                'meta_title' => 'Fraud Awareness - CheckoutPay',
                'meta_description' => 'Protect your business from payment fraud and social engineering.',
                'order' => 14,
                'content' => self::contentFor('fraud-awareness', $u),
            ],
        ];
    }

    public static function contentFor(string $slug, ?string $lastUpdated = null): string
    {
        $file = self::FILE_SLUGS[$slug] ?? null;
        if ($file === null) {
            throw new \InvalidArgumentException("Unknown legal page slug: {$slug}");
        }

        $path = database_path('legal/'.$file);
        if (! is_readable($path)) {
            throw new \RuntimeException("Legal content file missing: {$path}");
        }

        return self::wrap((string) file_get_contents($path), $lastUpdated ?? self::LAST_UPDATED);
    }

    private static function wrap(string $html, string $lastUpdated): string
    {
        return str_replace('LAST_UPDATED_PLACEHOLDER', htmlspecialchars($lastUpdated, ENT_QUOTES, 'UTF-8'), $html);
    }

    public static function syncToDatabase(): void
    {
        foreach (self::all() as $slug => $data) {
            \App\Models\Page::updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $data['title'],
                    'content' => $data['content'],
                    'meta_title' => $data['meta_title'],
                    'meta_description' => $data['meta_description'],
                    'is_published' => true,
                    'order' => $data['order'],
                ]
            );
        }
    }
}
