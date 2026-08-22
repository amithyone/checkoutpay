<?php

namespace Tests\Unit\Consumer;

use App\Models\Setting;
use App\Models\WhatsappWallet;
use App\Services\Consumer\ConsumerWalletTransferService;
use App\Services\Whatsapp\WhatsappWalletSelfBankTransferService;
use App\Services\WhatsappWalletBankPayoutService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class ConsumerTransferFeeQuoteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $schema = Schema::connection('sqlite');
        if (! $schema->hasTable('settings')) {
            $schema->create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('type')->nullable();
                $table->string('group')->nullable();
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }

        foreach ([
            'whatsapp_self_bank_transfer_fee_enabled',
            'whatsapp_self_bank_transfer_fee_percent',
            'whatsapp_self_bank_transfer_fixed_fee',
            'whatsapp_self_bank_transfer_max_fee',
        ] as $key) {
            Cache::forget('setting_'.$key);
        }

        Setting::set('whatsapp_self_bank_transfer_fee_enabled', true, 'boolean', 'whatsapp', 'test');
        Setting::set('whatsapp_self_bank_transfer_fee_percent', 2.0, 'float', 'whatsapp', 'test');
        Setting::set('whatsapp_self_bank_transfer_fixed_fee', 0, 'float', 'whatsapp', 'test');
        Setting::set('whatsapp_self_bank_transfer_max_fee', 500, 'float', 'whatsapp', 'test');

        if (! $schema->hasTable('whatsapp_wallets')) {
            $schema->create('whatsapp_wallets', function (Blueprint $table) {
                $table->id();
                $table->string('phone_e164', 32)->nullable();
                $table->string('mevon_virtual_account_number', 32)->nullable();
                $table->unsignedTinyInteger('tier')->default(1);
                $table->string('status', 32)->default('active');
                $table->timestamps();
            });
        }
    }

    public function test_p2p_quote_is_always_free(): void
    {
        $wallet = new WhatsappWallet([
            'phone_e164' => '2348012345678',
            'kyc_fname' => 'Ada',
            'kyc_lname' => 'Okafor',
        ]);

        $svc = app(ConsumerWalletTransferService::class);
        $result = $svc->feeQuote($wallet, 'p2p', 2000, 'personal', null, null, '08098765432');

        $this->assertTrue($result['ok']);
        $this->assertSame(0.0, $result['data']['fee_amount']);
        $this->assertSame(2000.0, $result['data']['total_debit']);
        $this->assertSame('No fee', $result['data']['fee_label']);
        $this->assertFalse($result['data']['self_transfer']);
    }

    public function test_bank_self_fintech_quote_matches_self_service_math(): void
    {
        $wallet = new WhatsappWallet([
            'phone_e164' => '2348012345678',
            'kyc_fname' => 'Ada',
            'kyc_lname' => 'Okafor',
        ]);

        $bankPayout = Mockery::mock(WhatsappWalletBankPayoutService::class);
        $bankPayout->shouldReceive('isNameEnquiryAvailable')->andReturn(false);
        $this->app->instance(WhatsappWalletBankPayoutService::class, $bankPayout);

        $svc = app(ConsumerWalletTransferService::class);
        $result = $svc->feeQuote(
            $wallet,
            'bank',
            10000,
            'personal',
            '100004',
            '8012345678',
        );

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['data']['self_transfer']);
        $this->assertSame(200.0, $result['data']['fee_amount']);
        $this->assertSame(10000.0, $result['data']['total_debit']);
        $this->assertSame(9800.0, $result['data']['payout_amount']);
        $this->assertSame('from_amount', $result['data']['fee_mode']);
        $this->assertSame('self_transfer', $result['data']['reason']);
        $this->assertSame('₦200.00', $result['data']['fee_label']);
    }

    public function test_bank_other_person_is_free_without_name_enquiry(): void
    {
        $wallet = new WhatsappWallet([
            'phone_e164' => '2348012345678',
            'kyc_fname' => 'Ada',
            'kyc_lname' => 'Okafor',
        ]);

        $bankPayout = Mockery::mock(WhatsappWalletBankPayoutService::class);
        $bankPayout->shouldReceive('isNameEnquiryAvailable')->andReturn(false);
        $this->app->instance(WhatsappWalletBankPayoutService::class, $bankPayout);

        $svc = app(ConsumerWalletTransferService::class);
        $result = $svc->feeQuote($wallet, 'bank', 5000, 'personal', '058', '0123456789');

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['data']['self_transfer']);
        $this->assertSame(0.0, $result['data']['fee_amount']);
        $this->assertSame('No fee', $result['data']['fee_label']);
    }

    public function test_zero_percent_self_transfer_is_waived(): void
    {
        Setting::set('whatsapp_self_bank_transfer_fee_percent', 0, 'float', 'whatsapp', 'test');

        $wallet = new WhatsappWallet([
            'phone_e164' => '2348012345678',
            'kyc_fname' => 'Ada',
            'kyc_lname' => 'Okafor',
        ]);

        $bankPayout = Mockery::mock(WhatsappWalletBankPayoutService::class);
        $bankPayout->shouldReceive('isNameEnquiryAvailable')->andReturn(false);
        $this->app->instance(WhatsappWalletBankPayoutService::class, $bankPayout);

        $svc = app(ConsumerWalletTransferService::class);
        $result = $svc->feeQuote($wallet, 'bank', 10000, 'personal', '100004', '8012345678');

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['data']['self_transfer']);
        $this->assertSame(0.0, $result['data']['fee_amount']);
        $this->assertTrue($result['data']['fee_waived']);
        $this->assertSame('self_transfer', $result['data']['reason']);
    }

    public function test_self_service_default_percent_is_zero(): void
    {
        Setting::query()->where('key', 'whatsapp_self_bank_transfer_fee_percent')->delete();
        Cache::forget('setting_whatsapp_self_bank_transfer_fee_percent');
        config(['whatsapp.self_bank_transfer_fee_percent' => 0]);

        $svc = app(WhatsappWalletSelfBankTransferService::class);
        $this->assertSame(0.0, $svc->feePercent());
    }
}
