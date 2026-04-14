<?php

namespace App\Services\Whatsapp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EvolutionWhatsAppClient
{
    public function sendText(string $instanceName, string $numberDigits, string $text): bool
    {
        $base = WhatsappEvolutionConfigResolver::baseUrl();
        $key = WhatsappEvolutionConfigResolver::apiKey();
        $instanceName = $instanceName !== '' ? $instanceName : WhatsappEvolutionConfigResolver::defaultInstance();

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
        $base = WhatsappEvolutionConfigResolver::baseUrl();
        $key = WhatsappEvolutionConfigResolver::apiKey();
        $instanceName = $instanceName !== '' ? $instanceName : WhatsappEvolutionConfigResolver::defaultInstance();

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
}
