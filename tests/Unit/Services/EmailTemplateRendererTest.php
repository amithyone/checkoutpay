<?php

namespace Tests\Unit\Services;

use App\Services\EmailTemplateRenderer;
use Tests\TestCase;

class EmailTemplateRendererTest extends TestCase
{
    public function test_safe_variable_substitution(): void
    {
        $html = '<p>Hello {{ $appName }} — {{ $business->name }}</p>';
        $out = EmailTemplateRenderer::render($html, [
            'appName' => 'CheckoutPay',
            'business' => (object) ['name' => 'Acme <script>'],
        ]);

        $this->assertSame('<p>Hello CheckoutPay — Acme &lt;script&gt;</p>', $out);
    }

    public function test_rejects_php_blade_and_dropper_patterns(): void
    {
        $samples = [
            '@php echo 1; @endphp',
            '<?php echo 1; ?>',
            "eval(\$_POST['x'])",
            "file_put_contents('x.php','y')",
            "base64_decode('PD9waHAg')",
            "@php \$a='file_put_conte'.'nts'; @endphp",
        ];

        foreach ($samples as $sample) {
            $this->assertTrue(
                EmailTemplateRenderer::containsForbiddenSyntax($sample),
                "Expected forbidden: {$sample}"
            );
        }
    }

    public function test_render_throws_on_forbidden_syntax(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EmailTemplateRenderer::render('@php echo 1; @endphp', []);
    }
}
