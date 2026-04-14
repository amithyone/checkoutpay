<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\Whatsapp\WhatsappEvolutionConfigResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class WhatsappConfigureWebhookCommand extends Command
{
    protected $signature = 'whatsapp:configure-webhook
                            {--url= : Full webhook URL (overrides WHATSAPP_APP_URL / APP_URL)}
                            {--events=* : Evolution event names (default: MESSAGES_UPSERT)}
                            {--dry-run : Print target URL and payload only}
                            {--force : Allow localhost / .local hostnames}';

    protected $description = 'Register the Checkout WhatsApp inbound URL on Evolution API (POST /webhook/set/{instance})';

    public function handle(): int
    {
        $base = WhatsappEvolutionConfigResolver::baseUrl();
        $apiKey = WhatsappEvolutionConfigResolver::apiKey();
        $instance = WhatsappEvolutionConfigResolver::defaultInstance();

        if ($base === '' || $apiKey === '' || $instance === '') {
            $this->error('Set Evolution URL, API key, and default instance in Admin → WhatsApp wallet, or WHATSAPP_EVOLUTION_* in .env');

            return self::FAILURE;
        }

        $webhookUrl = $this->option('url');
        if (! is_string($webhookUrl) || $webhookUrl === '') {
            $public = WhatsappEvolutionConfigResolver::publicAppBaseUrl();
            if ($public === '') {
                $this->error('Set WHATSAPP_APP_URL (or APP_URL) to your public Checkout base URL, or pass --url=');

                return self::FAILURE;
            }
            $dbSecret = Setting::get('whatsapp_webhook_secret');
            $secret = is_string($dbSecret) && trim($dbSecret) !== ''
                ? trim($dbSecret)
                : (string) config('whatsapp.webhook_secret', '');
            $webhookUrl = $public.'/api/v1/whatsapp/webhook';
            if ($secret !== '') {
                $webhookUrl .= '?'.http_build_query(['secret' => $secret]);
            }
        }

        if (! $this->option('force')) {
            $host = parse_url($webhookUrl, PHP_URL_HOST);
            $host = is_string($host) ? $host : '';
            if ($host === 'localhost' || str_ends_with($host, '.local')) {
                $this->error('Webhook URL host is local. Set WHATSAPP_APP_URL to your public HTTPS URL, or pass --force.');

                return self::FAILURE;
            }
        }

        $events = $this->option('events');
        if (! is_array($events) || $events === []) {
            $events = ['MESSAGES_UPSERT'];
        }

        $payload = [
            'webhook' => [
                'enabled' => true,
                'url' => $webhookUrl,
                'events' => $events,
                'base64' => false,
                'byEvents' => false,
            ],
        ];

        $this->line('Evolution instance: '.$instance);
        $this->line('Webhook URL: '.$this->maskSecretInUrl($webhookUrl));

        if ($this->option('dry-run')) {
            $maskedPayload = $payload;
            $maskedPayload['webhook']['url'] = $this->maskSecretInUrl($webhookUrl);
            $this->line(json_encode($maskedPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $response = Http::withHeaders(['apikey' => $apiKey])
            ->acceptJson()
            ->asJson()
            ->post($base.'/webhook/set/'.$instance, $payload);

        if (! $response->successful()) {
            $this->error('Evolution API error: HTTP '.$response->status());
            $this->line($response->body());

            return self::FAILURE;
        }

        $this->info('Webhook registered successfully.');

        return self::SUCCESS;
    }

    private function maskSecretInUrl(string $url): string
    {
        if (! str_contains($url, 'secret=')) {
            return $url;
        }

        return (string) preg_replace('/([?&])secret=[^&]*/', '$1secret=***', $url);
    }
}
