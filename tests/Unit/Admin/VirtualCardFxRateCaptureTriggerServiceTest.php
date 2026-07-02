<?php

namespace Tests\Unit\Admin;

use App\Models\Setting;
use App\Services\Admin\MevonPayFxRateTrackerService;
use App\Services\Admin\VirtualCardFxRateCaptureTriggerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VirtualCardFxRateCaptureTriggerServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'virtual_card.fx_payment_capture_enabled' => true,
            'virtual_card.fx_payment_capture_every' => 50,
        ]);

        Setting::set('virtual_card_fx_payment_assign_count', 48, 'integer', 'virtual_card', 'test');
    }

    public function test_increments_without_capture_before_threshold(): void
    {
        $tracker = $this->createMock(MevonPayFxRateTrackerService::class);
        $tracker->expects($this->never())->method('captureScheduledSnapshot');
        $this->app->instance(MevonPayFxRateTrackerService::class, $tracker);

        app(VirtualCardFxRateCaptureTriggerService::class)->recordPaymentAccountAssigned();

        $this->assertSame(49, app(VirtualCardFxRateCaptureTriggerService::class)->currentAssignmentCount());
    }

    public function test_captures_on_threshold_payment(): void
    {
        Setting::set('virtual_card_fx_payment_assign_count', 49, 'integer', 'virtual_card', 'test');

        $tracker = $this->createMock(MevonPayFxRateTrackerService::class);
        $tracker->expects($this->once())
            ->method('captureScheduledSnapshot')
            ->with('payment_milestone')
            ->willReturn(['ok' => true, 'message' => 'FX snapshot captured.']);
        $this->app->instance(MevonPayFxRateTrackerService::class, $tracker);

        app(VirtualCardFxRateCaptureTriggerService::class)->recordPaymentAccountAssigned();

        $this->assertSame(50, app(VirtualCardFxRateCaptureTriggerService::class)->currentAssignmentCount());
    }
}
