<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SeoCanonicalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://check-outpay.com']);
    }

    #[Test]
    public function dedicated_cms_page_redirects_from_page_slug_url(): void
    {
        $this->assertNotNull(Page::where('slug', 'privacy-policy')->first());

        $response = $this->get('/page/privacy-policy');

        $response->assertRedirect('/privacy-policy');
        $response->assertStatus(301);
    }

    #[Test]
    public function marketing_pages_include_self_referencing_canonical(): void
    {
        foreach ([
            '/faqs',
            '/contact',
            '/status',
            '/tickets',
            '/marketplace',
        ] as $path) {
            $response = $this->get($path);
            $response->assertOk();
            $response->assertSee(
                '<link rel="canonical" href="'.rtrim((string) config('app.url'), '/').$path.'">',
                false
            );
        }
    }

    #[Test]
    public function faqs_search_query_uses_base_faqs_canonical(): void
    {
        $response = $this->get('/faqs?q=woocommerce');

        $response->assertOk();
        $response->assertSee(
            '<link rel="canonical" href="'.rtrim((string) config('app.url'), '/').'/faqs">',
            false
        );
    }

    #[Test]
    public function sitemap_uses_top_level_path_for_dedicated_cms_pages(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringContainsString('/privacy-policy', $content);
        $this->assertStringNotContainsString('/page/privacy-policy', $content);
    }

    #[Test]
    public function public_path_for_page_slug_prefers_dedicated_routes(): void
    {
        $this->assertSame('/privacy-policy', Seo::publicPathForPageSlug('privacy-policy'));
        $this->assertSame('/page/custom-page', Seo::publicPathForPageSlug('custom-page'));
    }
}
