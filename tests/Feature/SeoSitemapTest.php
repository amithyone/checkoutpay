<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SeoSitemapTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function sitemap_includes_terms_and_conditions_not_legacy_path(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringContainsString('/terms-and-conditions', $content);
        $this->assertStringNotContainsString('/terms-of-service', $content);
    }

    #[Test]
    public function sitemap_includes_key_integration_pages(): void
    {
        $response = $this->get('/sitemap.xml');
        $content = $response->getContent();

        foreach ([
            '/wordpress-plugin',
            '/api-docs',
            '/developers/program',
            '/faqs',
            '/whatsapp-wallet',
        ] as $path) {
            $this->assertStringContainsString($path, $content, "Missing sitemap path: {$path}");
        }
    }

    #[Test]
    public function robots_txt_references_sitemap(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $this->assertStringContainsString('Sitemap:', $response->getContent());
    }

    #[Test]
    public function faqs_page_supports_search_query(): void
    {
        $response = $this->get('/faqs?q=woocommerce');

        $response->assertOk();
        $response->assertSee('WordPress', false);
    }

    #[Test]
    public function llms_txt_mentions_integrations(): void
    {
        $response = $this->get('/llms.txt');

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringContainsString('WordPress', $content);
        $this->assertStringContainsString('Developer Program', $content);
        $this->assertStringContainsString('/faqs', $content);
    }

    #[Test]
    public function checkout_logo_exists_for_og_image(): void
    {
        $this->assertFileExists(public_path('checkout-logo.png'));
    }
}
