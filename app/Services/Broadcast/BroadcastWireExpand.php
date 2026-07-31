<?php

namespace App\Services\Broadcast;

/**
 * Expand compact BLE wire JSON into a signed verify envelope.
 */
class BroadcastWireExpand
{
    /** @var list<string> */
    private const PAYLOAD_ROOT_KEYS = [
        'protocol_version',
        'connectivity',
        'timestamp_ms',
        'session_uuid_v4',
        'terminal_id',
        'transaction_details',
        'account_info_public_display',
        'offline_settlement',
        'broadcast_kind',
        'wallet_receive',
    ];

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function normalizeForVerify(array $body): array
    {
        if (isset($body['payload']) && is_array($body['payload'])) {
            return $this->normalizeEnvelope($body);
        }

        $wirePayload = $body['p'] ?? null;
        $wireSig = $body['sig'] ?? null;
        if (is_array($wirePayload) && is_string($wireSig) && $wireSig !== '') {
            return $this->normalizeEnvelope([
                'payload' => $wirePayload,
                'signature' => $wireSig,
                'signature_alg' => $body['alg'] ?? $body['signature_alg'] ?? 'ed25519',
            ]);
        }

        $signature = $body['signature'] ?? null;
        if (is_string($signature) && $signature !== ''
            && is_string($body['session_uuid_v4'] ?? null)
            && is_string($body['terminal_id'] ?? null)) {
            $payload = [];
            foreach (self::PAYLOAD_ROOT_KEYS as $key) {
                if (array_key_exists($key, $body)) {
                    $payload[$key] = $body[$key];
                }
            }

            return $this->normalizeEnvelope([
                'payload' => $payload,
                'signature' => $signature,
                'signature_alg' => $body['signature_alg'] ?? $body['alg'] ?? 'ed25519',
            ]);
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $envelope
     * @return array<string, mixed>
     */
    private function normalizeEnvelope(array $envelope): array
    {
        $alg = (string) ($envelope['signature_alg'] ?? $envelope['signatureAlg'] ?? $envelope['alg'] ?? 'ed25519');

        return [
            'payload' => $envelope['payload'],
            'signature_alg' => $alg !== '' ? $alg : 'ed25519',
            'signature' => (string) ($envelope['signature'] ?? $envelope['sig'] ?? ''),
        ];
    }
}
