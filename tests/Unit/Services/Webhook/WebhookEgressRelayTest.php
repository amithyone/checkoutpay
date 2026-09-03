<?php

namespace Tests\Unit\Services\Webhook;

use App\Models\Payment;
use App\Services\Webhook\WebhookEgressRelay;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookEgressRelayTest extends TestCase
{
    public function test_sign_is_stable(): void
    {
        $sig = WebhookEgressRelay::sign('1700000000', 'nonce-1', '{"a":1}', 'secret');
        $this->assertSame(64, strlen($sig));
        $this->assertSame(
            WebhookEgressRelay::sign('1700000000', 'nonce-1', '{"a":1}', 'secret'),
            $sig
        );
    }

    public function test_client_disabled_without_secret(): void
    {
        config([
            'checkout.webhook_egress.relay_client_enabled' => true,
            'checkout.webhook_egress.relay_url' => 'https://check-outpay.com/api/v1/internal/webhook-egress',
            'checkout.webhook_egress.relay_secret' => '',
        ]);

        $this->assertFalse(WebhookEgressRelay::clientEnabled());
    }

    public function test_failed_http_keeps_full_response_body(): void
    {
        $body = "<?php\nParse error: syntax error, unexpected token in webhook.php on line 12\n#0 /var/www/shop/webhook.php(12)";

        Http::fake([
            'https://merchant.example/webhook' => Http::response($body, 500, ['Content-Type' => 'text/html; charset=UTF-8']),
        ]);

        $result = WebhookEgressRelay::deliverDirect('https://merchant.example/webhook', [
            'event' => 'payment.approved',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame(500, $result['status']);
        $this->assertSame($body, $result['response_body']);
        $this->assertStringContainsString('HTTP 500', $result['error']);
    }

    public function test_payment_webhook_failure_details_exposes_response_body(): void
    {
        $payment = new Payment;
        $payment->webhook_last_error = json_encode([
            [
                'url' => 'https://merchant.example/webhook',
                'http_status' => 500,
                'response_body' => "Parse error: unexpected token\n<?php echo 1;",
                'error' => 'HTTP 500 Internal Server Error',
                'via' => 'direct',
            ],
        ]);

        $details = $payment->webhookFailureDetails();
        $this->assertCount(1, $details);
        $this->assertSame(500, $details[0]['http_status']);
        $this->assertSame("Parse error: unexpected token\n<?php echo 1;", $details[0]['response_body']);
    }
}
