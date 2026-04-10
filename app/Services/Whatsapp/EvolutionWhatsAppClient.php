<?php

namespace App\Services\Whatsapp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EvolutionWhatsAppClient
{
    public function sendText(string $instanceName, string $numberDigits, string $text): bool
    {
        $base = config('whatsapp.evolution.base_url');
        $key = config('whatsapp.evolution.api_key');

        if ($base === '' || $key === '' || $instanceName === '') {
            Log::warning('whatsapp.evolution: missing base_url, api_key, or instance');

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
}
