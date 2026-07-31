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
        'session_kind',
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
     * @param  array<string, mixed>  $wire
     * @return array<string, mixed>
     */
    public function expandCompactWirePayload(array $wire): array
    {
        $amountKobo = 0;
        if (array_key_exists('amt', $wire) && $wire['amt'] !== null && $wire['amt'] !== '') {
            $amountKobo = (int) $wire['amt'];
        }

        $expanded = [
            'protocol_version' => is_numeric($wire['v'] ?? null) ? (float) $wire['v'] : 2.1,
            'timestamp_ms' => (int) ($wire['ts'] ?? 0),
            'session_uuid_v4' => (string) ($wire['sid'] ?? ''),
            'terminal_id' => (string) ($wire['tid'] ?? ''),
            'transaction_details' => [
                'total_amount_ngn' => $amountKobo,
            ],
        ];

        if (isset($wire['msk']) && (string) $wire['msk'] !== '') {
            $expanded['account_info_public_display'] = [
                'masked_account_suffix' => (string) $wire['msk'],
            ];
        }

        if (isset($wire['k']) && (string) $wire['k'] !== '') {
            $expanded['session_kind'] = (string) $wire['k'];
        }

        return $expanded;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function isCompactWirePayload(array $payload): bool
    {
        if (isset($payload['protocol_version']) || isset($payload['session_uuid_v4']) || isset($payload['terminal_id'])) {
            return false;
        }

        return isset($payload['v']) || isset($payload['sid']) || isset($payload['tid']);
    }

    /**
     * @param  array<string, mixed>  $envelope
     * @return array<string, mixed>
     */
    private function normalizeEnvelope(array $envelope): array
    {
        $alg = (string) ($envelope['signature_alg'] ?? $envelope['signatureAlg'] ?? $envelope['alg'] ?? 'ed25519');

        $payload = $envelope['payload'];
        if (is_array($payload) && $this->isCompactWirePayload($payload)) {
            $payload = $this->expandCompactWirePayload($payload);
        }

        return [
            'payload' => $payload,
            'signature_alg' => $alg !== '' ? $alg : 'ed25519',
            'signature' => (string) ($envelope['signature'] ?? $envelope['sig'] ?? ''),
        ];
    }
}
