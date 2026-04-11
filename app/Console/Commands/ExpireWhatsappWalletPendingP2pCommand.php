<?php

namespace App\Console\Commands;

use App\Services\Whatsapp\WhatsappWalletPendingP2pService;
use Illuminate\Console\Command;

class ExpireWhatsappWalletPendingP2pCommand extends Command
{
    protected $signature = 'whatsapp-wallet:expire-pending-p2p';

    protected $description = 'Refund WhatsApp P2P holds when the recipient did not open WALLET in time';

    public function handle(WhatsappWalletPendingP2pService $service): int
    {
        $n = $service->expireAndRefundDue();
        if ($n > 0) {
            $this->info("Refunded {$n} unclaimed P2P hold(s).");
        }

        return self::SUCCESS;
    }
}
