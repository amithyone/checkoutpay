<?php

namespace App\Console\Commands;

use App\Services\Admin\MevonPayFxRateTrackerService;
use Illuminate\Console\Command;

class CaptureVirtualCardFxRatesCommand extends Command
{
    protected $signature = 'virtual-cards:capture-fx-rates';

    protected $description = 'Capture hourly MevonPay and Cashwyre FX rate snapshots for the admin rate tracker';

    public function handle(MevonPayFxRateTrackerService $tracker): int
    {
        if (! config('virtual_card.fx_hourly_capture_enabled', false)) {
            $this->line('Hourly FX capture is disabled (VIRTUAL_CARD_FX_HOURLY_CAPTURE_ENABLED=false).');

            return self::SUCCESS;
        }

        $result = $tracker->captureHourlySnapshot();

        if ($result['ok'] ?? false) {
            if ($result['skipped'] ?? false) {
                $this->line($result['message']);

                return self::SUCCESS;
            }

            $this->info($result['message']);
            if (isset($result['mevon_mid'])) {
                $this->line('Mevon mid: ₦'.number_format((float) $result['mevon_mid'], 2));
            }
            if (isset($result['cashwyre_mid']) && $result['cashwyre_mid'] !== null) {
                $this->line('Cashwyre mid: ₦'.number_format((float) $result['cashwyre_mid'], 2));
            }

            return self::SUCCESS;
        }

        $this->error($result['message'] ?? 'Hourly FX capture failed.');

        return self::FAILURE;
    }
}
