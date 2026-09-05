<?php

namespace App\Services\Whatsapp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Meta WhatsApp Cloud API outbound client (drop-in for EvolutionWhatsAppClient).
 */
class MetaCloudWhatsAppClient extends EvolutionWhatsAppClient
{
    public function sendText(string $instanceName, string $numberDigits, string $text): bool
    {
        if (WalletConversationCapture::isActive()) {
            WalletConversationCapture::append($text);

            return true;
        }

        $phoneNumberId = WhatsappCloudConfigResolver::phoneNumberIdForInstance($instanceName);
        $token = WhatsappCloudConfigResolver::accessToken();

        if ($phoneNumberId === '' || $token === '') {
            Log::warning('whatsapp.cloud: missing phone_number_id or access_token', [
                'instance' => $instanceName,
                'has_phone_number_id' => $phoneNumberId !== '',
                'has_token' => $token !== '',
            ]);

            return false;
        }

        $to = PhoneNormalizer::digitsOnly($numberDigits) ?? $numberDigits;
        $url = WhatsappCloudConfigResolver::graphBaseUrl().'/'.$phoneNumberId.'/messages';

        try {
            $response = $this->graphHttp($token)
                ->timeout(25)
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $to,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => $text,
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('whatsapp.cloud: sendText failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('whatsapp.cloud: sendText exception', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Official Cloud API template send — required to reach users who have not messaged first.
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

        $name = trim($name);
        $language = $this->normalizeTemplateLanguage($language);
        if ($name === '') {
            return false;
        }

        $phoneNumberId = WhatsappCloudConfigResolver::phoneNumberIdForInstance($instanceName);
        $token = WhatsappCloudConfigResolver::accessToken();

        if ($phoneNumberId === '' || $token === '') {
            Log::warning('whatsapp.cloud: missing phone_number_id or access_token for sendTemplate', [
                'instance' => $instanceName,
            ]);

            return false;
        }

        $to = PhoneNormalizer::digitsOnly($numberDigits) ?? $numberDigits;
        $url = WhatsappCloudConfigResolver::graphBaseUrl().'/'.$phoneNumberId.'/messages';

        try {
            $response = $this->graphHttp($token)
                ->timeout(25)
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $to,
                    'type' => 'template',
                    'template' => [
                        'name' => $name,
                        'language' => ['code' => $language],
                        'components' => $components,
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('whatsapp.cloud: sendTemplate failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'name' => $name,
                    'language' => $language,
                    'to' => $to,
                ]);

                return false;
            }

            $json = $response->json();
            Log::info('whatsapp.cloud: sendTemplate accepted', [
                'name' => $name,
                'language' => $language,
                'to' => $to,
                'wa_id' => data_get($json, 'contacts.0.wa_id'),
                'wamid' => data_get($json, 'messages.0.id'),
                'message_status' => data_get($json, 'messages.0.message_status'),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('whatsapp.cloud: sendTemplate exception', ['error' => $e->getMessage()]);

            return false;
        }
    }

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

        $phoneNumberId = WhatsappCloudConfigResolver::phoneNumberIdForInstance($instanceName);
        $token = WhatsappCloudConfigResolver::accessToken();

        if ($phoneNumberId === '' || $token === '') {
            Log::warning('whatsapp.cloud: missing phone_number_id or access_token for sendMedia');

            return false;
        }

        $media = preg_replace('#^data:[^;]+;base64,#i', '', $base64Media) ?? $base64Media;
        $media = trim($media);
        if ($media === '') {
            Log::warning('whatsapp.cloud: sendMedia empty media payload');

            return false;
        }

        $binary = base64_decode($media, true);
        if ($binary === false) {
            Log::warning('whatsapp.cloud: sendMedia invalid base64');

            return false;
        }

        $mediaId = $this->uploadMedia($phoneNumberId, $token, $binary, $mimetype, $fileName ?? 'media.bin');
        if ($mediaId === null) {
            return false;
        }

        $to = PhoneNormalizer::digitsOnly($numberDigits) ?? $numberDigits;
        $type = strtolower($mediatype);
        if (! in_array($type, ['image', 'video', 'document', 'audio'], true)) {
            $type = 'document';
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => $type,
            $type => array_filter([
                'id' => $mediaId,
                'caption' => $caption,
                'filename' => $type === 'document' ? ($fileName ?? 'file.bin') : null,
            ], fn ($v) => $v !== null && $v !== ''),
        ];

        try {
            $url = WhatsappCloudConfigResolver::graphBaseUrl().'/'.$phoneNumberId.'/messages';
            $response = $this->graphHttp($token)
                ->timeout(60)
                ->post($url, $payload);

            if (! $response->successful()) {
                Log::warning('whatsapp.cloud: sendMedia failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('whatsapp.cloud: sendMedia exception', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function uploadMedia(string $phoneNumberId, string $token, string $binary, string $mimetype, string $fileName): ?string
    {
        try {
            $url = WhatsappCloudConfigResolver::graphBaseUrl().'/'.$phoneNumberId.'/media';
            $response = $this->graphHttp($token)
                ->timeout(60)
                ->attach('file', $binary, $fileName)
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'type' => $mimetype !== '' ? $mimetype : 'application/octet-stream',
                ]);

            if (! $response->successful()) {
                Log::warning('whatsapp.cloud: media upload failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $id = $response->json('id');

            return is_string($id) && $id !== '' ? $id : null;
        } catch (\Throwable $e) {
            Log::error('whatsapp.cloud: media upload exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function graphHttp(string $token): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withOptions([
            'query' => WhatsappCloudConfigResolver::graphAuthQuery($token),
        ]);
    }
}
