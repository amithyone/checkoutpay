<?php

namespace App\Services\Admin;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;

final class VirtualCardFxRateCaptureTriggerService
{
    public function isEnabled(): bool
    {
        return (bool) config('virtual_card.fx_payment_capture_enabled', true);
    }

    public function threshold(): int
    {
        return max(1, (int) config('virtual_card.fx_payment_capture_every', 50));
    }

    /**
     * Call after a payment is created with an assigned account number.
     */
    public function recordPaymentAccountAssigned(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $count = $this->incrementAssignmentCount();
        if ($count % $this->threshold() !== 0) {
            return;
        }

        try {
            $result = app(MevonPayFxRateTrackerService::class)->captureScheduledSnapshot('payment_milestone');
            if (! ($result['ok'] ?? false) && ! ($result['skipped'] ?? false)) {
                Log::warning('virtual_card.fx_payment_capture_failed', [
                    'message' => $result['message'] ?? 'Unknown error',
                    'count' => $count,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('virtual_card.fx_payment_capture_failed', [
                'error' => $e->getMessage(),
                'count' => $count,
            ]);
        }
    }

    public function currentAssignmentCount(): int
    {
        $stored = Setting::get('virtual_card_fx_payment_assign_count');

        return is_numeric($stored) ? (int) $stored : 0;
    }

    private function incrementAssignmentCount(): int
    {
        $next = $this->currentAssignmentCount() + 1;
        Setting::set(
            'virtual_card_fx_payment_assign_count',
            $next,
            'integer',
            'virtual_card',
            'Payment account assignments since last FX rate snapshot capture',
        );

        return $next;
    }
}
