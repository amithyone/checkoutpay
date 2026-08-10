<?php

namespace Tests\Unit\Admin;

use App\Models\Business;
use App\Models\Payment;
use App\Services\Admin\PaymentImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_map_row_maps_legacy_success_to_approved(): void
    {
        $service = app(PaymentImportService::class);
        $mapped = $service->mapRow([
            'legacy_id' => '99',
            'transaction_id' => 'REF-ABC',
            'external_reference' => '134180',
            'amount' => '8000.00',
            'status' => 'success',
            'payment_method' => 'xtrapay',
            'payer_name' => 'Ada',
            'payer_email' => 'ada@example.com',
            'charge' => '100',
            'received_amount' => '8100',
            'created_at' => '2025-07-11 03:39:41',
            'updated_at' => '2025-07-11 03:40:00',
            'metadata_json' => '{"deposit_id":134180}',
            'source_system' => 'checzspw_payment',
        ], 7);

        $this->assertNotNull($mapped);
        $this->assertSame('REF-ABC', $mapped['transaction_id']);
        $this->assertSame(Payment::STATUS_APPROVED, $mapped['status']);
        $this->assertSame(PaymentImportService::SOURCE, $mapped['payment_source']);
        $this->assertSame(8000.0, $mapped['amount']);
        $this->assertSame(100.0, $mapped['total_charges']);
        $this->assertSame('Ada', $mapped['payer_name']);
    }

    public function test_import_creates_payment_without_crediting_balance(): void
    {
        Storage::fake('local');
        $business = Business::create([
            'name' => 'Import Biz',
            'email' => 'import-biz-'.uniqid().'@test.com',
            'api_key' => 'pk_imp_'.uniqid(),
            'is_active' => true,
            'balance' => 50,
        ]);

        $csv = implode("\n", [
            'transaction_id,amount,status,payer_name,external_reference,payment_method,charge,received_amount,created_at,updated_at,source_system',
            'LEGACY-1,2500.00,approved,Bola,ext-1,payvibe,50,2550,2025-07-01 10:00:00,2025-07-01 10:01:00,checzspw_payment',
        ])."\n";

        Storage::disk('local')->put('payment-imports/test.csv', $csv);

        $result = app(PaymentImportService::class)->importFromStoragePath('payment-imports/test.csv', [
            'business_id' => $business->id,
            'dry_run' => false,
            'credit_balances' => false,
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['created']);
        $this->assertSame(1, Payment::count());
        $payment = Payment::first();
        $this->assertSame('LEGACY-1', $payment->transaction_id);
        $this->assertSame(Payment::STATUS_APPROVED, $payment->status);
        $this->assertSame(PaymentImportService::SOURCE, $payment->payment_source);
        $this->assertSame(50.0, (float) $business->fresh()->balance);
    }

    public function test_credit_balances_increments_when_enabled(): void
    {
        Storage::fake('local');
        $business = Business::create([
            'name' => 'Credit Biz',
            'email' => 'credit-biz-'.uniqid().'@test.com',
            'api_key' => 'pk_cr_'.uniqid(),
            'is_active' => true,
            'balance' => 0,
        ]);

        Storage::disk('local')->put('payment-imports/credit.csv',
            "transaction_id,amount,status\nCRED-1,1000,approved\n"
        );

        $result = app(PaymentImportService::class)->importFromStoragePath('payment-imports/credit.csv', [
            'business_id' => $business->id,
            'credit_balances' => true,
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame(1000.0, (float) $business->fresh()->balance);
    }
}
