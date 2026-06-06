<?php

namespace App\Console\Commands;

use App\Models\VirtualCardRequest;
use App\Services\Consumer\VirtualCardRequestSupersedeService;
use Illuminate\Console\Command;

class SupersedeStaleVirtualCardAttemptsCommand extends Command
{
    protected $signature = 'virtual-card:supersede-stale-attempts
        {--wallet-id= : Only process one whatsapp_wallet_id}';

    protected $description = 'Mark older failed/open card attempts as superseded when a wallet already has an active card';

    public function handle(VirtualCardRequestSupersedeService $supersede): int
    {
        $walletId = $this->option('wallet-id');

        $winners = VirtualCardRequest::query()
            ->whereNotNull('card_external_id')
            ->where('card_external_id', '!=', '')
            ->whereIn('status', [
                VirtualCardRequest::STATUS_SUBMITTED,
                VirtualCardRequest::STATUS_ACTIVE,
            ])
            ->when($walletId, fn ($query) => $query->where('whatsapp_wallet_id', (int) $walletId))
            ->orderBy('whatsapp_wallet_id')
            ->orderByDesc('id')
            ->get()
            ->unique('whatsapp_wallet_id');

        if ($winners->isEmpty()) {
            $this->info('No operable virtual cards found.');

            return self::SUCCESS;
        }

        $total = 0;
        foreach ($winners as $winner) {
            $count = $supersede->supersedeStaleAttempts($winner);
            if ($count > 0) {
                $this->line("Wallet #{$winner->whatsapp_wallet_id}: superseded {$count} attempt(s) using request #{$winner->id}");
                $total += $count;
            }
        }

        $this->info("Done. Superseded {$total} stale attempt(s) across ".$winners->count().' wallet(s).');

        return self::SUCCESS;
    }
}
