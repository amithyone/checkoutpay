<?php

namespace App\Services\Whatsapp;

use Illuminate\Http\Request;

/**
 * Routes webhook payloads to Evolution or Meta Cloud parsers.
 */
final class WhatsappWebhookPayloadRouter
{
    public function __construct(
        private EvolutionWebhookPayloadParser $evolutionParser,
        private MetaCloudWebhookPayloadParser $metaParser,
    ) {}

    /**
     * @return list<array{instance: string, remote_jid: string, phone_e164: string, text: string, from_me: bool}>
     */
    public function extractInboundMessages(Request $request): array
    {
        if ($this->shouldUseMetaParser($request)) {
            return $this->metaParser->extractInboundMessages($request);
        }

        return $this->evolutionParser->extractInboundMessages($request);
    }

    private function shouldUseMetaParser(Request $request): bool
    {
        if (WhatsappCloudConfigResolver::isEnabled()) {
            return true;
        }

        $payload = $request->all();

        return ($payload['object'] ?? '') === 'whatsapp_business_account';
    }
}
