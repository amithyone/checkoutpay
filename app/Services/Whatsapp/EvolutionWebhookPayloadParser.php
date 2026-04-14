<?php

namespace App\Services\Whatsapp;

use Illuminate\Http\Request;

/**
 * Tolerant parser for Evolution API webhook bodies (shape varies by version / integration).
 */
class EvolutionWebhookPayloadParser
{
    /**
     * @return list<array{instance: string, remote_jid: string, phone_e164: string, text: string, from_me: bool}>
     */
    public function extractInboundMessages(Request $request): array
    {
        $payload = $request->all();
        $out = [];

        if ($payload === []) {
            return [];
        }

        // Some proxies send raw JSON array of events
        if (array_is_list($payload) && isset($payload[0]) && is_array($payload[0])) {
            foreach ($payload as $ev) {
                $out = array_merge($out, $this->fromEvent(is_array($ev) ? $ev : []));
            }

            return $out;
        }

        // Single event object
        if (isset($payload['event']) || isset($payload['data'])) {
            return $this->fromEvent($payload);
        }

        // Bare message-shaped payload
        if (isset($payload['key']['remoteJid'])) {
            $instance = (string) ($payload['instance'] ?? config('whatsapp.evolution.instance', ''));
            $parsed = $this->fromMessageRow($payload, $instance);
            if ($parsed !== null) {
                $out[] = $parsed;
            }
        }

        return $out;
    }

    /**
     * @return list<array{instance: string, remote_jid: string, phone_e164: string, text: string, from_me: bool}>
     */
    private function fromEvent(array $ev): array
    {
        $event = strtolower((string) ($ev['event'] ?? ''));
        if ($event !== '' && ! str_contains($event, 'upsert')) {
            return [];
        }

        $instance = (string) ($ev['instance'] ?? config('whatsapp.evolution.instance', ''));
        $data = $ev['data'] ?? null;

        if (! is_array($data)) {
            return [];
        }

        $rows = [];

        if (isset($data['messages']) && is_array($data['messages'])) {
            foreach ($data['messages'] as $row) {
                if (is_array($row)) {
                    $p = $this->fromMessageRow($row, $instance);
                    if ($p !== null) {
                        $rows[] = $p;
                    }
                }
            }

            return $rows;
        }

        if (isset($data['key']['remoteJid'])) {
            $p = $this->fromMessageRow($data, $instance);
            if ($p !== null) {
                $rows[] = $p;
            }
        }

        return $rows;
    }

    /**
     * @return ?array{instance: string, remote_jid: string, phone_e164: string, text: string, from_me: bool}
     */
    private function fromMessageRow(array $row, string $instance): ?array
    {
        $key = $row['key'] ?? [];
        if (! is_array($key)) {
            return null;
        }

        $remoteJid = (string) ($key['remoteJid'] ?? '');
        if ($remoteJid === '' || str_ends_with($remoteJid, '@g.us')) {
            return null;
        }

        if (! empty($key['fromMe'])) {
            return null;
        }

        $phone = PhoneNormalizer::e164FromRemoteJid($remoteJid);
        if ($phone === null) {
            return null;
        }

        $text = $this->extractText($row['message'] ?? []);
        if ($text === null || $text === '') {
            return null;
        }

        return [
            'instance' => $instance,
            'remote_jid' => $remoteJid,
            'phone_e164' => $phone,
            'text' => $text,
            'from_me' => false,
        ];
    }

    private function extractText(mixed $message): ?string
    {
        if (! is_array($message)) {
            return null;
        }

        if (isset($message['editedMessage']['message']) && is_array($message['editedMessage']['message'])) {
            return $this->extractText($message['editedMessage']['message']);
        }
        if (isset($message['ephemeralMessage']['message']) && is_array($message['ephemeralMessage']['message'])) {
            return $this->extractText($message['ephemeralMessage']['message']);
        }
        if (isset($message['viewOnceMessage']['message']) && is_array($message['viewOnceMessage']['message'])) {
            return $this->extractText($message['viewOnceMessage']['message']);
        }

        if (isset($message['conversation']) && is_string($message['conversation'])) {
            $t = trim($message['conversation']);

            return $t !== '' ? $t : null;
        }

        if (isset($message['extendedTextMessage']['text']) && is_string($message['extendedTextMessage']['text'])) {
            $t = trim($message['extendedTextMessage']['text']);

            return $t !== '' ? $t : null;
        }

        foreach (['imageMessage', 'videoMessage', 'documentMessage'] as $k) {
            if (isset($message[$k]['caption']) && is_string($message[$k]['caption'])) {
                $t = trim($message[$k]['caption']);

                return $t !== '' ? $t : null;
            }
        }

        if (isset($message['buttonsResponseMessage']) && is_array($message['buttonsResponseMessage'])) {
            $b = $message['buttonsResponseMessage'];
            if (isset($b['selectedDisplayText']) && is_string($b['selectedDisplayText'])) {
                $t = trim($b['selectedDisplayText']);

                return $t !== '' ? $t : null;
            }
            if (isset($b['selectedButtonId']) && is_string($b['selectedButtonId'])) {
                $t = trim($b['selectedButtonId']);

                return $t !== '' ? $t : null;
            }
        }

        if (isset($message['listResponseMessage']['singleSelectReply']['selectedRowId']) && is_string($message['listResponseMessage']['singleSelectReply']['selectedRowId'])) {
            $t = trim($message['listResponseMessage']['singleSelectReply']['selectedRowId']);

            return $t !== '' ? $t : null;
        }

        return null;
    }
}
