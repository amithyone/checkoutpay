<?php

namespace Tests\Unit;

use App\Support\FaqCatalog;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FaqCatalogTest extends TestCase
{
    #[Test]
    public function each_category_has_at_least_six_questions(): void
    {
        foreach (FaqCatalog::categories() as $slug => $meta) {
            $count = count(FaqCatalog::forCategory($slug));
            $this->assertGreaterThanOrEqual(
                6,
                $count,
                "Category [{$slug}] ({$meta['label']}) should have at least 6 FAQs, found {$count}"
            );
        }
    }

    #[Test]
    public function search_finds_wordpress_and_developer_topics(): void
    {
        $woo = FaqCatalog::search('woocommerce');
        $this->assertNotEmpty($woo);
        $this->assertContains(
            'wordpress-plugin',
            array_column($woo, 'category')
        );

        $dev = FaqCatalog::search('developer program');
        $this->assertNotEmpty($dev);
        $this->assertContains(
            'developer-program',
            array_column($dev, 'category')
        );
    }

    #[Test]
    public function faq_page_json_ld_contains_questions(): void
    {
        $items = FaqCatalog::forCategory('api');
        $graph = FaqCatalog::faqPageJsonLd($items);

        $this->assertSame('FAQPage', $graph['@type']);
        $this->assertGreaterThanOrEqual(6, count($graph['mainEntity'] ?? []));
    }

    #[Test]
    public function format_answer_replaces_route_placeholders(): void
    {
        $item = [
            'a' => 'Apply at {apply_url} and read {api_docs_url}.',
        ];

        $formatted = FaqCatalog::formatAnswer($item);

        $this->assertStringContainsString(route('developers.program.apply'), $formatted);
        $this->assertStringContainsString(route('api-docs'), $formatted);
    }
}
