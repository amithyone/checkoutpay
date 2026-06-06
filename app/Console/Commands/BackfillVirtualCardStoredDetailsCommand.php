<?php

namespace App\Console\Commands;

use App\Models\VirtualCardRequest;
use App\Services\Consumer\VirtualCardStoredDetailsService;
use Illuminate\Console\Command;

class BackfillVirtualCardStoredDetailsCommand extends Command
{
    protected $signature = 'virtual-card:backfill-stored-details
        {--wallet-id= : Only backfill cards for this whatsapp_wallet_id}
        {--request-id= : Only backfill this virtual_card_requests.id}';

    protected $description = 'Copy Mevon card.created webhook PAN/CVV/expiry from logs into encrypted card_details_payload';

    public function handle(VirtualCardStoredDetailsService $storedDetails): int
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

        $filled = 0;
        foreach ($rows as $row) {
            if ($storedDetails->backfillRequest($row)) {
                $filled++;
                $this->line("Request #{$row->id}: stored card details.");
            } else {
                $this->warn("Request #{$row->id}: no webhook card details found in logs/payload.");
            }
        }

        $this->info("Backfill complete. {$filled}/{$rows->count()} request(s) now have stored card details.");

        return self::SUCCESS;
    }
}
