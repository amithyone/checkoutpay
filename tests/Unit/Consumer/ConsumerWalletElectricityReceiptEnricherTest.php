<?php

namespace Tests\Unit\Consumer;

use App\Models\WhatsappWalletTransaction;
use App\Services\Consumer\ConsumerWalletElectricityReceiptEnricher;
use Tests\TestCase;

class ConsumerWalletElectricityReceiptEnricherTest extends TestCase
{
    public function test_maps_token_and_meter_into_receipt_fields(): void
    {
        config([
            'vtu.electricity_discos' => [
                ['id' => 'ikeja-electric', 'label' => 'Ikeja (IKEDC)'],
            ],
        ]);

        $tx = new WhatsappWalletTransaction([
            'type' => WhatsappWalletTransaction::TYPE_VTU_ELECTRICITY,
            'amount' => 8000,
        ]);
        $tx->id = 1;

        $row = (new ConsumerWalletElectricityReceiptEnricher())->enrich($tx, [
            'type' => WhatsappWalletTransaction::TYPE_VTU_ELECTRICITY,
            'amount' => 8000,
            'meta' => [
                'service_id' => 'ikeja-electric',
                'customer_name' => 'NNA-ENYI NWAFOR RITA',
                'meter_number' => '45059940655',
                'electricity_token' => '6782-1401-5731-3278-6890',
                'electricity_units' => '52.3',
                'vtu_request_id' => 'CP-EL-PEBKTI0QLH3R5Q',
                'vtu_order_id' => 6025742,
                'vtu_pending' => false,
            ],
        ]);

        $this->assertSame('6782-1401-5731-3278-6890', $row['electricity_token'] ?? null);
        $this->assertSame('45059940655', $row['counterparty_account_number'] ?? null);
        $this->assertStringContainsString('Token: 6782-1401-5731-3278-6890', (string) ($row['narration'] ?? ''));
        $this->assertSame('CP-EL-PEBKTI0QLH3R5Q', $row['session_id'] ?? null);
        $this->assertSame('success', $row['meta']['status'] ?? null);
        $this->assertStringContainsString('NNA-ENYI NWAFOR RITA', (string) ($row['meta']['label'] ?? ''));
    }

    public function test_pending_order_sets_pending_narration_and_status(): void
    {
        $tx = new WhatsappWalletTransaction([
            'type' => WhatsappWalletTransaction::TYPE_VTU_ELECTRICITY,
        ]);

        $row = (new ConsumerWalletElectricityReceiptEnricher())->enrich($tx, [
            'type' => WhatsappWalletTransaction::TYPE_VTU_ELECTRICITY,
            'meta' => [
                'meter_number' => '12345678901',
                'vtu_pending' => true,
            ],
        ]);

        $this->assertTrue((bool) ($row['vtu_pending'] ?? false));
        $this->assertSame('pending', $row['meta']['status'] ?? null);
        $this->assertStringContainsString('Token pending', (string) ($row['narration'] ?? ''));
    }
}
