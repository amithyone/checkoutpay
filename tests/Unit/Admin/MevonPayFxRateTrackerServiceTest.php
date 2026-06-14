<?php

namespace Tests\Unit\Admin;

use App\Models\MevonPayFxRateSnapshot;
use App\Services\Admin\MevonPayFxRateTrackerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MevonPayFxRateTrackerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_live_creates_snapshot_with_change(): void
    {
        $tracker = app(MevonPayFxRateTrackerService::class);
        $tracker->recordLive(1370.0);
        $tracker->recordLive(1380.0);

        $this->assertSame(2, MevonPayFxRateSnapshot::query()->count());

        $latest = MevonPayFxRateSnapshot::query()->orderByDesc('id')->first();
        $this->assertNotNull($latest);
        $this->assertEquals(1380.0, $latest->mevon_mid);
        $this->assertEquals(10.0, $latest->change_abs);
        $this->assertEqualsWithDelta(0.7299, $latest->change_pct, 0.01);
    }

    public function test_dedupes_unchanged_rate_within_window(): void
    {
        $tracker = app(MevonPayFxRateTrackerService::class);
        $tracker->recordLive(1370.0);
        $tracker->recordLive(1370.0);

        $this->assertSame(1, MevonPayFxRateSnapshot::query()->count());
    }

    public function test_record_published_stores_sell_and_buy(): void
    {
        $tracker = app(MevonPayFxRateTrackerService::class);
        $tracker->recordPublished(1400.0, 1415.0, 1370.0, 'mevon_live', 1400.0);

        $row = MevonPayFxRateSnapshot::query()->first();
        $this->assertNotNull($row);
        $this->assertEquals(1415.0, $row->sell_rate);
        $this->assertEquals(1370.0, $row->buy_rate);
        $this->assertSame('mevon_live', $row->source);
    }

    public function test_dashboard_returns_series_for_range(): void
    {
        MevonPayFxRateSnapshot::query()->create([
            'recorded_at' => now()->subHours(2),
            'mevon_mid' => 1360,
            'published_mid' => 1360,
            'sell_rate' => 1375,
            'buy_rate' => 1330,
            'source' => 'mevon_live',
        ]);
        MevonPayFxRateSnapshot::query()->create([
            'recorded_at' => now()->subHour(),
            'mevon_mid' => 1370,
            'published_mid' => 1370,
            'sell_rate' => 1385,
            'buy_rate' => 1340,
            'source' => 'mevon_live',
            'change_abs' => 10,
            'change_pct' => 0.7353,
        ]);

        $dashboard = app(MevonPayFxRateTrackerService::class)->dashboard(
            request()->merge(['range' => '24h'])
        );

        $this->assertSame('24h', $dashboard['range']);
        $this->assertGreaterThanOrEqual(2, count($dashboard['series']));
        $this->assertEquals(1370.0, $dashboard['current']['mevon_mid']);
        $this->assertGreaterThan(0, $dashboard['stats']['count']);
        $this->assertTrue($dashboard['live_poll']);
    }

    public function test_dashboard_supports_short_live_ranges(): void
    {
        MevonPayFxRateSnapshot::query()->create([
            'recorded_at' => now()->subMinutes(30),
            'mevon_mid' => 1360,
            'published_mid' => 1360,
            'source' => 'mevon_live',
        ]);

        foreach (['1h', '6h', '7h', '12h'] as $range) {
            $dashboard = app(MevonPayFxRateTrackerService::class)->dashboard(
                request()->merge(['range' => $range])
            );

            $this->assertSame($range, $dashboard['range']);
            $this->assertTrue($dashboard['live_poll']);
            $this->assertGreaterThanOrEqual(1, count($dashboard['series']));
        }
    }

    public function test_live_data_returns_json_payload_without_fresh_fetch(): void
    {
        MevonPayFxRateSnapshot::query()->create([
            'recorded_at' => now()->subMinutes(5),
            'mevon_mid' => 1390,
            'published_mid' => 1390,
            'sell_rate' => 1405,
            'buy_rate' => 1360,
            'source' => 'mevon_live',
        ]);

        $payload = app(MevonPayFxRateTrackerService::class)->liveData(
            request()->merge(['range' => '1h']),
            fetchFresh: false,
        );

        $this->assertTrue($payload['ok']);
        $this->assertSame('1h', $payload['range']);
        $this->assertEquals(1390.0, $payload['current']['mevon_mid']);
        $this->assertGreaterThanOrEqual(1, count($payload['series']));
        $this->assertTrue($payload['live_poll']);
        $this->assertSame(60, $payload['poll_seconds']);
    }

    public function test_calculator_rates_match_tracker_current_sell_and_buy(): void
    {
        MevonPayFxRateSnapshot::query()->create([
            'recorded_at' => now()->subMinutes(2),
            'mevon_mid' => 1378.08,
            'published_mid' => 1378.08,
            'sell_rate' => 1393.08,
            'buy_rate' => 1348.08,
            'source' => 'mevon_live',
        ]);

        $tracker = app(MevonPayFxRateTrackerService::class);
        $dashboard = $tracker->dashboard(request()->merge(['range' => '1h']));
        $calculator = $tracker->calculatorRates(fetchFresh: false);

        $this->assertTrue($calculator['ok']);
        $this->assertEquals($dashboard['current']['sell_rate'], $calculator['sell_rate']);
        $this->assertEquals($dashboard['current']['buy_rate'], $calculator['buy_rate']);
        $this->assertSame(60, $calculator['poll_seconds']);
    }

    public function test_calculator_rates_compute_from_mid_when_snapshot_lacks_sell_buy(): void
    {
        \App\Models\Setting::set('virtual_card_fx_sell_profit_ngn', 15, 'float', 'virtual_card', 'test');
        \App\Models\Setting::set('virtual_card_fx_buy_profit_ngn', 30, 'float', 'virtual_card', 'test');

        MevonPayFxRateSnapshot::query()->create([
            'recorded_at' => now()->subMinute(),
            'mevon_mid' => 1400,
            'published_mid' => 1400,
            'source' => 'mevon_live',
        ]);

        $calculator = app(MevonPayFxRateTrackerService::class)->calculatorRates(fetchFresh: false);

        $this->assertEquals(1415.0, $calculator['sell_rate']);
        $this->assertEquals(1370.0, $calculator['buy_rate']);
    }
}
