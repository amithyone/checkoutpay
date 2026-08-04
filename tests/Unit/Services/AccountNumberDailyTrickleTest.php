<?php

namespace Tests\Unit\Services;

use App\Models\AccountNumber;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\AccountNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AccountNumberDailyTrickleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Setting::set('account_daily_trickle_limit_ngn', 3600, 'float', 'payment');
        Setting::set('account_release_after_success_minutes', 1, 'integer', 'payment');
    }

    public function test_assignment_trickles_to_next_account_after_daily_limit(): void
    {
        $first = AccountNumber::create([
            'account_number' => '1111111111',
            'account_name' => 'Pool One',
            'bank_name' => 'Test',
            'is_pool' => true,
            'is_active' => true,
        ]);
        $second = AccountNumber::create([
            'account_number' => '2222222222',
            'account_name' => 'Pool Two',
            'bank_name' => 'Test',
            'is_pool' => true,
            'is_active' => true,
        ]);

        Payment::create([
            'transaction_id' => 'tx-daily-1',
            'amount' => 3600,
            'received_amount' => 3600,
            'status' => Payment::STATUS_APPROVED,
            'account_number' => $first->account_number,
            'matched_at' => now('Africa/Lagos'),
            'payer_name' => 'Someone',
            'webhook_url' => 'https://example.com/webhook',
        ]);

        Cache::flush();

        $assigned = app(AccountNumberService::class)->assignAccountNumber();

        $this->assertNotNull($assigned);
        $this->assertSame($second->account_number, $assigned->account_number);
    }
}
