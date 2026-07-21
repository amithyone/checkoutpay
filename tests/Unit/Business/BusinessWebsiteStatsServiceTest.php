<?php

namespace Tests\Unit\Business;

use App\Models\Business;
use App\Models\BusinessWebsite;
use App\Models\Payment;
use App\Services\Business\BusinessWebsiteStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BusinessWebsiteStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_breakdown_includes_inferred_unattributed_payments_by_webhook(): void
    {
        $business = Business::query()->create([
            'name' => 'Infer Co',
            'email' => 'infer@example.test',
            'password' => Hash::make('secret'),
            'business_id' => 'INFER1',
            'phone' => '08055556666',
            'balance' => 0,
        ]);

        $website = BusinessWebsite::query()->create([
            'business_id' => $business->id,
            'website_url' => 'shop-a.example.test',
            'webhook_url' => 'https://shop-a.example.test/webhook',
            'is_approved' => true,
        ]);

        Payment::query()->create([
            'transaction_id' => 'TX-INF-001',
            'amount' => 4000,
            'business_receives' => 3900,
            'business_id' => $business->id,
            'business_website_id' => null,
            'webhook_url' => 'https://shop-a.example.test/webhook',
            'status' => Payment::STATUS_APPROVED,
            'payment_source' => Payment::SOURCE_INTERNAL,
            'matched_at' => now(),
        ]);

        $rows = app(BusinessWebsiteStatsService::class)->buildDashboardBreakdown($business->fresh(['websites']));

        $this->assertCount(1, $rows);
        $this->assertEquals(3900.0, (float) $rows[0]['total_revenue']);
        $this->assertSame($website->id, $rows[0]['website']->id);
    }

    public function test_dashboard_breakdown_splits_payments_by_website_and_includes_unattributed(): void
    {
        $business = Business::query()->create([
            'name' => 'Multi Site Co',
            'email' => 'multi@example.test',
            'password' => Hash::make('secret'),
            'business_id' => 'MULTI1',
            'phone' => '08011112222',
            'balance' => 0,
        ]);

        $siteA = BusinessWebsite::query()->create([
            'business_id' => $business->id,
            'website_url' => 'https://shop-a.example.test',
            'webhook_url' => 'https://shop-a.example.test/webhook',
            'is_approved' => true,
        ]);

        $siteB = BusinessWebsite::query()->create([
            'business_id' => $business->id,
            'website_url' => 'https://shop-b.example.test',
            'webhook_url' => 'https://shop-b.example.test/webhook',
            'is_approved' => true,
        ]);

        Payment::query()->create([
            'transaction_id' => 'TX-A-001',
            'amount' => 10000,
            'business_receives' => 9500,
            'business_id' => $business->id,
            'business_website_id' => $siteA->id,
            'status' => Payment::STATUS_APPROVED,
            'payment_source' => Payment::SOURCE_INTERNAL,
            'matched_at' => now(),
        ]);

        Payment::query()->create([
            'transaction_id' => 'TX-B-001',
            'amount' => 5000,
            'business_receives' => 4800,
            'business_id' => $business->id,
            'business_website_id' => $siteB->id,
            'status' => Payment::STATUS_APPROVED,
            'payment_source' => Payment::SOURCE_INTERNAL,
            'matched_at' => now(),
        ]);

        Payment::query()->create([
            'transaction_id' => 'TX-OTHER-001',
            'amount' => 3000,
            'business_receives' => 2900,
            'business_id' => $business->id,
            'business_website_id' => null,
            'status' => Payment::STATUS_APPROVED,
            'payment_source' => Payment::SOURCE_INTERNAL,
            'matched_at' => now(),
        ]);

        $rows = app(BusinessWebsiteStatsService::class)->buildDashboardBreakdown($business);

        $this->assertCount(3, $rows);

        $byWebsiteId = collect($rows)->keyBy(fn (array $row) => $row['is_unattributed'] ? 'unattributed' : $row['website']->id);

        $this->assertEquals(9500.0, (float) $byWebsiteId[$siteA->id]['total_revenue']);
        $this->assertEquals(4800.0, (float) $byWebsiteId[$siteB->id]['total_revenue']);
        $this->assertTrue($byWebsiteId['unattributed']['is_unattributed']);
        $this->assertEquals(2900.0, (float) $byWebsiteId['unattributed']['total_revenue']);

        $breakdownTotal = array_sum(array_column($rows, 'total_revenue'));
        $businessTotal = Payment::query()
            ->where('business_id', $business->id)
            ->where('status', Payment::STATUS_APPROVED)
            ->sum(\DB::raw('COALESCE(business_receives, amount)'));

        $this->assertEquals((float) $businessTotal, $breakdownTotal);
    }

    public function test_dashboard_breakdown_omits_unattributed_row_when_none_exist(): void
    {
        $business = Business::query()->create([
            'name' => 'Single Site Co',
            'email' => 'single@example.test',
            'password' => Hash::make('secret'),
            'business_id' => 'SINGLE1',
            'phone' => '08033334444',
            'balance' => 0,
        ]);

        $website = BusinessWebsite::query()->create([
            'business_id' => $business->id,
            'website_url' => 'https://only.example.test',
            'webhook_url' => 'https://only.example.test/webhook',
            'is_approved' => true,
        ]);

        Payment::query()->create([
            'transaction_id' => 'TX-ONLY-001',
            'amount' => 2000,
            'business_receives' => 1900,
            'business_id' => $business->id,
            'business_website_id' => $website->id,
            'status' => Payment::STATUS_APPROVED,
            'payment_source' => Payment::SOURCE_INTERNAL,
            'matched_at' => now(),
        ]);

        $rows = app(BusinessWebsiteStatsService::class)->buildDashboardBreakdown($business);

        $this->assertCount(1, $rows);
        $this->assertFalse($rows[0]['is_unattributed']);
    }
}
