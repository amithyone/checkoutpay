<?php

namespace App\Services\Whatsapp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EvolutionWhatsAppClient
{
    public function sendText(string $instanceName, string $numberDigits, string $text): bool
    {
        if (WalletConversationCapture::isActive()) {
            WalletConversationCapture::append($text);

            return true;
        }

        $base = WhatsappEvolutionConfigResolver::baseUrl();
        $key = WhatsappEvolutionConfigResolver::apiKey();
        $instanceName = $instanceName !== '' ? $instanceName : WhatsappEvolutionConfigResolver::defaultInstance();
        $instanceName = WhatsappEvolutionConfigResolver::canonicalInstanceName($instanceName);

        if ($base === '' || $key === '' || $instanceName === '') {
            Log::warning('whatsapp.evolution: missing base_url, api_key, or instance', [
                'has_base' => $base !== '',
                'has_key' => $key !== '',
                'has_instance' => $instanceName !== '',
            ]);

            return false;
        }

        $url = $base.'/message/sendText/'.rawurlencode($instanceName);

        try {
            $response = Http::withHeaders([
                'apikey' => $key,
                'Content-Type' => 'application/json',
            ])
                ->timeout(25)
                ->post($url, [
                    'number' => $numberDigits,
                    'text' => $text,
                ]);

            if (! $response->successful()) {
                Log::warning('whatsapp.evolution: sendText failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('whatsapp.evolution: sendText exception', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Send image, video, or document via Evolution API (base64 body).
     *
     * @param  string  $mediatype  image|video|document|audio (Evolution expects lowercase)
     */
    public function sendMedia(
        string $instanceName,
        string $numberDigits,
        string $mediatype,
        string $mimetype,
        string $base64Media,
        ?string $caption = null,
        ?string $fileName = null,
    ): bool {
        if (WalletConversationCapture::isActive()) {
            $hint = trim((string) $caption);
            WalletConversationCapture::append($hint !== '' ? "[{$mediatype}] {$hint}" : '['.$mediatype.']');

            return true;
        }

        $base = WhatsappEvolutionConfigResolver::baseUrl();
        $key = WhatsappEvolutionConfigResolver::apiKey();
        $instanceName = $instanceName !== '' ? $instanceName : WhatsappEvolutionConfigResolver::defaultInstance();
        $instanceName = WhatsappEvolutionConfigResolver::canonicalInstanceName($instanceName);

        if ($base === '' || $key === '' || $instanceName === '') {
            Log::warning('whatsapp.evolution: missing base_url, api_key, or instance', [
                'has_base' => $base !== '',
                'has_key' => $key !== '',
                'has_instance' => $instanceName !== '',
            ]);

            return false;
        }

        $media = preg_replace('#^data:[^;]+;base64,#i', '', $base64Media) ?? $base64Media;
        $media = trim($media);
        if ($media === '') {
            Log::warning('whatsapp.evolution: sendMedia empty media payload');

            return false;
        }

        $url = $base.'/message/sendMedia/'.rawurlencode($instanceName);
        $payload = [
            'number' => $numberDigits,
            'mediatype' => strtolower($mediatype),
            'mimetype' => $mimetype,
            'caption' => $caption ?? '',
            'media' => $media,
            'fileName' => $fileName ?? 'media.bin',
        ];

        try {
            $response = Http::withHeaders([
                'apikey' => $key,
                'Content-Type' => 'application/json',
            ])
                ->timeout(60)
                ->post($url, $payload);

            if (! $response->successful()) {
                Log::warning('whatsapp.evolution: sendMedia failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('whatsapp.evolution: sendMedia exception', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Send a Meta-approved WhatsApp template (Cloud API / Evolution Business).
     *
     * @param  list<array<string, mixed>>  $components
     */
    public function sendTemplate(
        string $instanceName,
        string $numberDigits,
        string $name,
        string $language,
        array $components,
    ): bool {
        if (WalletConversationCapture::isActive()) {
            WalletConversationCapture::append('[template] '.$name);

            return true;
        }

        $base = WhatsappEvolutionConfigResolver::baseUrl();
        $key = WhatsappEvolutionConfigResolver::apiKey();
        $instanceName = $instanceName !== '' ? $instanceName : WhatsappEvolutionConfigResolver::defaultInstance();
        $instanceName = WhatsappEvolutionConfigResolver::canonicalInstanceName($instanceName);

        if ($base === '' || $key === '' || $instanceName === '' || trim($name) === '') {
            return false;
        }

        $url = $base.'/message/sendTemplate/'.rawurlencode($instanceName);

        try {
            $response = Http::withHeaders([
                'apikey' => $key,
                'Content-Type' => 'application/json',
            ])
                ->timeout(25)
                ->post($url, [
                    'number' => $numberDigits,
                    'name' => $name,
                    'language' => $language,
                    'components' => $components,
                ]);

            if (! $response->successful()) {
                Log::warning('whatsapp.evolution: sendTemplate failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'name' => $name,
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('whatsapp.evolution: sendTemplate exception', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Authentication OTP template — works without a 24-hour customer-care session.
     */
    public function sendAuthenticationOtp(string $instanceName, string $numberDigits, string $code): bool
    {
        $name = trim((string) config('whatsapp.otp.template_name', ''));
        if ($name === '') {
            return false;
        }

        $preferred = trim((string) config('whatsapp.otp.template_language', 'en')) ?: 'en';
        $languages = array_values(array_unique(array_filter([$preferred, 'en_US', 'en'])));
        $includeButton = (bool) config('whatsapp.otp.template_button', true);
        $code = preg_replace('/\D+/', '', $code) ?? $code;

        $bodyOnly = [
            [
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => $code],
                ],
            ],
        ];
        $withButton = array_merge($bodyOnly, [[
            'type' => 'button',
            'sub_type' => 'url',
            'index' => '0',
            'parameters' => [
                ['type' => 'text', 'text' => $code],
            ],
        ]]);

        foreach ($languages as $language) {
            if ($includeButton && $this->sendTemplate($instanceName, $numberDigits, $name, $language, $withButton)) {
                return true;
            }
            if ($this->sendTemplate($instanceName, $numberDigits, $name, $language, $bodyOnly)) {
                return true;
            }
        }

        return false;
    }
}
