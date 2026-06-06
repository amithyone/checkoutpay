<?php

namespace App\Console\Commands;

use App\Models\VirtualCardRequest;
use App\Models\VirtualCardRequestLog;
use App\Services\Consumer\ConsumerVirtualCardService;
use App\Services\Consumer\VirtualCardStoredDetailsService;
use App\Services\MevonPay\MevonPayCardApiClient;
use Illuminate\Console\Command;

class DebugVirtualCardStoredDetailsCommand extends Command
{
    protected $signature = 'virtual-card:debug-stored-details
        {request-id : virtual_card_requests.id}
        {--sync : Attempt full sync (logs + Mevon) after diagnostics}';

    protected $description = 'Diagnose why stored card details are missing for a virtual card request';

    public function handle(
        VirtualCardStoredDetailsService $storedDetails,
        MevonPayCardApiClient $cardApi,
        ConsumerVirtualCardService $cards,
    ): int {
        $row = VirtualCardRequest::query()->find((int) $this->argument('request-id'));
        if (! $row) {
            $this->error('Request not found.');

            return self::FAILURE;
        }

        $this->info("Request #{$row->id} status={$row->status} card_external_id={$row->card_external_id} wallet={$row->whatsapp_wallet_id}");

        $stored = $row->card_details_payload;
        $this->line('card_details_payload: '.(is_array($stored) ? 'present (encrypted)' : 'empty'));

        $response = is_array($row->response_payload) ? $row->response_payload : [];
        $webhook = $response['webhook'] ?? null;
        $this->line('response_payload.webhook: '.(is_array($webhook) ? 'yes' : 'no'));
        if (is_array($webhook)) {
            $data = is_array($webhook['data'] ?? null) ? $webhook['data'] : $webhook;
            $this->line('  webhook card_number: '.(isset($data['card_number']) ? 'yes' : 'no'));
            $this->line('  webhook card_id: '.(string) ($data['card_id'] ?? $data['cardId'] ?? '—'));
        }

        $logQuery = VirtualCardRequestLog::query()
            ->where(function ($query) use ($row) {
                $query->where('virtual_card_request_id', $row->id)
                    ->orWhere('whatsapp_wallet_id', $row->whatsapp_wallet_id);
            });

        $this->line('linked logs: '.$logQuery->count());

        foreach ($logQuery->orderByDesc('id')->limit(8)->get() as $log) {
            $ctx = is_array($log->context) ? $log->context : [];
            $hasPayload = is_array($ctx['raw_payload'] ?? null);
            $hasBody = is_string($ctx['raw_body'] ?? null) && trim($ctx['raw_body']) !== '';
            $snippet = json_encode($ctx['raw_payload'] ?? $ctx['raw_body'] ?? '');
            $hasNumber = is_string($snippet) && str_contains($snippet, 'card_number');
            $this->line("  log #{$log->id} {$log->event} payload={$hasPayload} body={$hasBody} card_number={$hasNumber}");
        }

        $cardId = trim((string) ($row->card_external_id ?? ''));
        if ($cardId !== '') {
            $orphan = VirtualCardRequestLog::query()
                ->whereRaw('CAST(context AS CHAR) LIKE ?', ['%'.$cardId.'%'])
                ->count();
            $this->line("logs mentioning card_id anywhere: {$orphan}");
        }

        $resolved = $storedDetails->resolveForRequest($row);
        $this->line('resolveForRequest: '.($resolved ? 'OK' : 'FAILED'));

        if (! $resolved && $cardId !== '' && $cardApi->isConfigured()) {
            $api = $cardApi->getCardDetails($cardId);
            $this->line('Mevon card details API: '.(($api['ok'] ?? false) ? 'OK' : 'FAILED — '.($api['message'] ?? 'unknown')));
            if ($api['ok'] ?? false) {
                $this->line('  provider card_code: '.(string) ($api['data']['card_code'] ?? '—'));
            }
        }

        $providerCode = $cards->syncProviderCardCode($row->fresh());
        $this->line('provider card_code resolve: '.($providerCode ?? 'FAILED'));

        if ($this->option('sync')) {
            $ok = $cards->syncStoredCardDetails($row->fresh());
            $this->line($ok ? 'Sync succeeded.' : 'Sync failed.');
        }

        return self::SUCCESS;
    }
}
