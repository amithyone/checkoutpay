<?php

namespace Tests\Unit;

use App\Services\MevonPay\MevonPayInboundWebhookRecorder;
use Tests\TestCase;

class MevonPayLedgerMetaTest extends TestCase
{
    public function test_ledger_meta_from_funding_success_payload(): void
    {
        $payload = [
            'event' => 'funding.success',
            'data' => [
                'account_number' => '8880324727',
                'amount' => 2575,
                'reference' => '100004260518170536160223787208',
                'sender' => 'DANIEL DAVID JOSEPH',
                'bank_name' => 'OPAY',
                'narration' => 'DONATING',
                'timestamp' => '2026-05-18 18:05:41.247',
            ],
        ];

        $meta = MevonPayInboundWebhookRecorder::ledgerMetaFromPayload($payload, 2575.0);

        $this->assertSame('mevonpay_funding', $meta['source']);
        $this->assertSame(2575.0, $meta['reported_amount']);
        $this->assertSame(30, $meta['mevon_inbound_fee']);
        $this->assertSame('DANIEL DAVID JOSEPH', $meta['payer_name']);
        $this->assertSame('OPAY', $meta['payer_bank']);
        $this->assertSame('8880324727', $meta['receive_account_number']);
        $this->assertSame('100004260518170536160223787208', $meta['mevon_reference']);
        $this->assertSame('DONATING', $meta['narration']);
        $this->assertSame('2026-05-18 18:05:41.247', $meta['bank_timestamp']);
    }
}
