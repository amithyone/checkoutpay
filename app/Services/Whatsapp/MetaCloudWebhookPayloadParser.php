<?php

namespace App\Services\Whatsapp;

use Illuminate\Http\Request;

/**
 * Parser for Meta WhatsApp Cloud API webhook payloads.
 *
 * @see https://developers.facebook.com/docs/whatsapp/cloud-api/webhooks/payload-examples
 */
class MetaCloudWebhookPayloadParser
{
    /**
     * @return list<array{instance: string, remote_jid: string, phone_e164: string, text: string, from_me: bool}>
     */
    public function extractInboundMessages(Request $request): array
    {
        $payload = $request->all();
        if (($payload['object'] ?? '') !== 'whatsapp_business_account') {
            return [];
        }

        $out = [];

        foreach ($payload['entry'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            foreach ($entry['changes'] ?? [] as $change) {
                if (! is_array($change) || ($change['field'] ?? '') !== 'messages') {
                    continue;
                }
                $value = $change['value'] ?? [];
                if (! is_array($value)) {
                    continue;
                }

                $phoneNumberId = (string) ($value['metadata']['phone_number_id'] ?? '');
                $instance = WhatsappCloudConfigResolver::instanceForPhoneNumberId($phoneNumberId);

                foreach ($value['messages'] ?? [] as $message) {
                    if (! is_array($message)) {
                        continue;
                    }
                    $parsed = $this->fromMessage($message, $instance);
                    if ($parsed !== null) {
                        $out[] = $parsed;
                    }
                }
            }
        }

        return $out;
    }

    /**
     * Delivery receipts Meta posts on the same webhook (`statuses`).
     *
     * @return list<array{wamid: string, status: string, recipient: string, error_code: int|null, error: string|null}>
     */
    public function extractStatuses(Request $request): array
    {
        $payload = $request->all();
        if (($payload['object'] ?? '') !== 'whatsapp_business_account') {
            return [];
        }

        $out = [];
        foreach ($payload['entry'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            foreach ($entry['changes'] ?? [] as $change) {
                if (! is_array($change) || ($change['field'] ?? '') !== 'messages') {
                    continue;
                }
                $value = $change['value'] ?? [];
                if (! is_array($value)) {
                    continue;
                }
                foreach ($value['statuses'] ?? [] as $status) {
                    if (! is_array($status)) {
                        continue;
                    }
                    $errors = $status['errors'][0] ?? [];
                    $out[] = [
                        'wamid' => (string) ($status['id'] ?? ''),
                        'status' => (string) ($status['status'] ?? ''),
                        'recipient' => (string) ($status['recipient_id'] ?? ''),
                        'error_code' => isset($errors['code']) ? (int) $errors['code'] : null,
                        'error' => isset($errors['message']) ? (string) $errors['message'] : (isset($errors['title']) ? (string) $errors['title'] : null),
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * @return ?array{instance: string, remote_jid: string, phone_e164: string, text: string, from_me: bool}
     */
    private function fromMessage(array $message, string $instance): ?array
    {
        $from = PhoneNormalizer::digitsOnly((string) ($message['from'] ?? ''));
        if ($from === null || $from === '') {
            return null;
        }

        $text = $this->extractText($message);
        if ($text === null || $text === '') {
            return null;
        }

        return [
            'instance' => $instance,
            'remote_jid' => $from.'@s.whatsapp.net',
            'phone_e164' => $from,
            'text' => $text,
            'from_me' => false,
        ];
    }

    private function extractText(array $message): ?string
    {
        $type = strtolower((string) ($message['type'] ?? ''));

        if ($type === 'text') {
            $body = trim((string) ($message['text']['body'] ?? ''));

            return $body !== '' ? $body : null;
        }

        if ($type === 'button') {
            $body = trim((string) ($message['button']['text'] ?? $message['button']['payload'] ?? ''));

            return $body !== '' ? $body : null;
        }

        if ($type === 'interactive') {
            $interactive = $message['interactive'] ?? [];
            if (! is_array($interactive)) {
                return null;
            }

            if (($interactive['type'] ?? '') === 'button_reply') {
                $body = trim((string) ($interactive['button_reply']['title'] ?? $interactive['button_reply']['id'] ?? ''));

                return $body !== '' ? $body : null;
            }

            if (($interactive['type'] ?? '') === 'list_reply') {
                $body = trim((string) ($interactive['list_reply']['title'] ?? $interactive['list_reply']['id'] ?? ''));

                return $body !== '' ? $body : null;
            }
        }

        return null;
    }
}
