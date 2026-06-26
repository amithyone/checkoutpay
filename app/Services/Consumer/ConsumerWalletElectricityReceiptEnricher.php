<?php

namespace App\Services\Consumer;

use App\Models\WhatsappWalletTransaction;

/**
 * Shape electricity VTU rows for the consumer app receipt/detail UI using fields
 * the existing client already reads (narration, label, beneficiary account, status).
 */
class ConsumerWalletElectricityReceiptEnricher
{
    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function enrich(WhatsappWalletTransaction $tx, array $row): array
    {
        if ($tx->type !== WhatsappWalletTransaction::TYPE_VTU_ELECTRICITY) {
            return $row;
        }

        $meta = is_array($row['meta'] ?? null) ? $row['meta'] : [];

        $token = trim((string) ($meta['electricity_token'] ?? ''));
        $meter = trim((string) ($meta['meter_number'] ?? ''));
        $units = trim((string) ($meta['electricity_units'] ?? ''));
        $customer = trim((string) ($meta['customer_name'] ?? ''));
        $discoId = trim((string) ($meta['service_id'] ?? ''));
        $pending = (bool) ($meta['vtu_pending'] ?? false);
        $refunded = (bool) ($meta['vtu_refunded'] ?? false);
        $orderId = $meta['vtu_order_id'] ?? null;
        $requestId = trim((string) ($meta['vtu_request_id'] ?? $meta['vtu_provider_reference'] ?? ''));
        $discoLabel = $this->discoLabel($discoId);

        $headline = 'Electricity';
        if ($customer !== '') {
            $headline .= ' · '.$customer;
        } elseif ($discoLabel !== '') {
            $headline .= ' · '.$discoLabel;
        }
        $meta['label'] = $headline;

        if ($discoId !== '') {
            $meta['service_id'] = $discoId;
        }

        if ($customer !== '' && trim((string) ($row['counterparty_account_name'] ?? '')) === '') {
            $row['counterparty_account_name'] = $customer;
        }

        if ($meter !== '') {
            $row['counterparty_account_number'] = $meter;
            $meta['account_number'] = $meter;
        }

        $narration = $this->buildNarration($token, $units, $orderId, $pending, $refunded);
        if ($narration !== '') {
            $row['narration'] = $narration;
            $meta['narration'] = $narration;
        }

        if ($refunded) {
            $meta['status'] = 'failed';
            $meta['failed'] = true;
        } elseif ($pending) {
            $meta['status'] = 'pending';
        } else {
            $meta['status'] = 'success';
        }

        if ($requestId !== '') {
            $row['session_id'] = $requestId;
            $row['vtu_request_id'] = $requestId;
        }

        if ($token !== '') {
            $row['electricity_token'] = $token;
        }
        if ($meter !== '') {
            $row['electricity_meter'] = $meter;
        }
        if ($units !== '') {
            $row['electricity_units'] = $units;
        }
        if ($discoId !== '') {
            $row['electricity_disco'] = $discoId;
        }
        if ($discoLabel !== '') {
            $row['electricity_disco_label'] = $discoLabel;
        }
        if ($customer !== '') {
            $row['electricity_customer_name'] = $customer;
        }
        if ($orderId !== null && $orderId !== '') {
            $row['vtu_order_id'] = $orderId;
        }

        $row['vtu_pending'] = $pending;
        $row['vtu_status'] = trim((string) ($meta['vtu_status'] ?? ''));
        $row['meta'] = $meta;

        return $row;
    }

    private function discoLabel(string $discoId): string
    {
        if ($discoId === '') {
            return '';
        }

        foreach ((array) config('vtu.electricity_discos', []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            if ((string) ($row['id'] ?? '') === $discoId) {
                return trim((string) ($row['label'] ?? $discoId));
            }
        }

        return $discoId;
    }

    private function buildNarration(
        string $token,
        string $units,
        mixed $orderId,
        bool $pending,
        bool $refunded,
    ): string {
        if ($refunded) {
            return 'Electricity payment failed and was refunded to your wallet.';
        }

        $parts = [];

        if ($token !== '') {
            $parts[] = 'Token: '.$token;
        } elseif ($pending) {
            $parts[] = 'Token pending — usually ready in 2–5 minutes. Pull to refresh or reopen this receipt.';
        }

        if ($units !== '') {
            $parts[] = 'Units: '.$units;
        }

        if ($orderId !== null && $orderId !== '') {
            $parts[] = 'Order: '.(string) $orderId;
        }

        return implode(' · ', $parts);
    }
}
