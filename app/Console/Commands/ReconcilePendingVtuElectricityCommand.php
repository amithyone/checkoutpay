<?php

namespace App\Console\Commands;

use App\Services\Whatsapp\WhatsappWalletVtuElectricityReconciliationService;
use Illuminate\Console\Command;

class ReconcilePendingVtuElectricityCommand extends Command
{
    protected $signature = 'vtu:reconcile-pending-electricity';

    protected $description = 'Requery VTU.ng for pending electricity orders and deliver tokens via WhatsApp';

    public function handle(WhatsappWalletVtuElectricityReconciliationService $service): int
    {
        if (! $service->isAvailable()) {
            $this->warn('VTU.ng is not configured; skipping.');

            return self::SUCCESS;
        }

        $stats = $service->reconcilePendingBatch();
        $this->info(sprintf(
            'Checked %d, completed %d, failed %d, notified %d, skipped %d.',
            $stats['checked'],
            $stats['completed'],
            $stats['failed'],
            $stats['notified'],
            $stats['skipped'],
        ));

        return self::SUCCESS;
    }
}
