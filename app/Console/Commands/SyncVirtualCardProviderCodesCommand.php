<?php

namespace App\Console\Commands;

use App\Models\VirtualCardRequest;
use App\Services\Consumer\ConsumerVirtualCardService;
use Illuminate\Console\Command;

class SyncVirtualCardProviderCodesCommand extends Command
{
    protected $signature = 'virtual-card:sync-provider-card-codes
        {--request-id= : Only sync this virtual_card_requests.id}
        {--wallet-id= : Only sync cards for this whatsapp_wallet_id}';

    protected $description = 'Resolve and store Mevon VCARD codes for freeze/topup/withdraw (cards stored with UUID card_id)';

    public function handle(ConsumerVirtualCardService $cards): int
    {
        $query = VirtualCardRequest::query()
            ->whereNotNull('card_external_id')
            ->where('card_external_id', '!=', '');

        if ($this->option('request-id')) {
            $query->where('id', (int) $this->option('request-id'));
        }

        if ($this->option('wallet-id')) {
            $query->where('whatsapp_wallet_id', (int) $this->option('wallet-id'));
        }

        $rows = $query->orderBy('id')->get();
        if ($rows->isEmpty()) {
            $this->warn('No virtual card requests matched.');

            return self::SUCCESS;
        }

        $synced = 0;
        foreach ($rows as $row) {
            $code = $cards->syncProviderCardCode($row);
            if ($code !== null) {
                $synced++;
                $this->line("Request #{$row->id}: {$code}");
            } else {
                $this->warn("Request #{$row->id}: could not resolve VCARD code for {$row->card_external_id}");
            }
        }

        $this->info("Sync complete. {$synced}/{$rows->count()} request(s) have a provider card code.");

        return self::SUCCESS;
    }
}
