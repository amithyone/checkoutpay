<?php

namespace App\Console\Commands;

use App\Models\VirtualCardRequest;
use App\Models\WhatsappWallet;
use App\Services\Consumer\ConsumerVirtualCardService;
use App\Services\MevonPay\MevonPayCardApiClient;
use Illuminate\Console\Command;

class ProbeVirtualCardMevonEndpointsCommand extends Command
{
    protected $signature = 'virtual-card:probe-mevon
        {--request-id= : virtual_card_requests.id (loads card id/code/reference from DB)}
        {--card-id= : Mevon card_id UUID for direct probe}
        {--card-code= : Mevon VCARD… code for transactions}
        {--request-id-mevon= : Mevon REQ… id for card_balance}';

    protected $description = 'Call Mevon card_balance, card_details, and card_transactions and print parsed responses';

    public function handle(
        MevonPayCardApiClient $cardApi,
        ConsumerVirtualCardService $cards,
    ): int {
        if (! $cardApi->isConfigured()) {
            $this->error('MevonPay is not configured (MEVONPAY_BASE_URL / MEVONPAY_SECRET_KEY).');

            return self::FAILURE;
        }

        $cardId = trim((string) $this->option('card-id'));
        $cardCode = trim((string) $this->option('card-code'));
        $mevonRequestId = trim((string) $this->option('request-id-mevon'));

        if ($this->option('request-id')) {
            $row = VirtualCardRequest::query()->find((int) $this->option('request-id'));
            if (! $row) {
                $this->error('virtual_card_requests row not found.');

                return self::FAILURE;
            }

            $this->info("DB request #{$row->id} wallet={$row->whatsapp_wallet_id} balance_usd={$row->card_balance_usd}");
            $this->line('provider_reference: '.(string) ($row->provider_reference ?? '—'));
            $this->line('card_external_id: '.(string) ($row->card_external_id ?? '—'));

            if ($cardId === '') {
                $cardId = trim((string) ($row->card_external_id ?? ''));
            }
            if ($mevonRequestId === '') {
                $mevonRequestId = trim((string) ($row->provider_reference ?? ''));
            }
            if ($cardCode === '') {
                $cardCode = (string) ($cards->backfillMevonCardCode($row) ?? '');
            }

            $wallet = WhatsappWallet::query()->find($row->whatsapp_wallet_id);
            if ($wallet) {
                $this->newLine();
                $this->info('Service: refreshProviderCardBalance');
                $cards->refreshProviderCardBalance($wallet);
                $row = $row->fresh();
                $this->line('DB balance after refresh: '.(string) ($row->card_balance_usd ?? '—'));

                $this->newLine();
                $this->info('Service: cardTransactions (page 1)');
                $tx = $cards->cardTransactions($wallet, 5, 1);
                $this->line(json_encode([
                    'ok' => $tx['ok'] ?? false,
                    'count' => count($tx['data'] ?? []),
                    'first' => ($tx['data'] ?? [])[0] ?? null,
                    'meta' => $tx['meta'] ?? null,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
        }

        $this->newLine();
        $this->info('=== Mevon POST /V1/card_details ===');
        if ($cardId === '') {
            $this->warn('Skipped (no --card-id).');
        } else {
            $this->printApiResult($cardApi->getCardDetails($cardId));
        }

        $this->newLine();
        $this->info('=== Mevon POST /V1/card_transactions ===');
        if ($cardCode === '') {
            $this->warn('Skipped (no --card-code).');
        } else {
            $this->printApiResult($cardApi->getCardTransactions($cardCode));
        }

        $this->newLine();
        $this->info('=== Mevon POST /V1/card_balance ===');
        if ($mevonRequestId === '') {
            $this->warn('Skipped (no REQ request_id — use --request-id-mevon or set provider_reference on the card).');
        } else {
            $this->printApiResult($cardApi->getCardBalance($mevonRequestId));
        }

        return self::SUCCESS;
    }

    /**
     * @param  array{ok?: bool, message?: string, data?: mixed, raw?: mixed, http_status?: int}  $result
     */
    private function printApiResult(array $result): void
    {
        $ok = (bool) ($result['ok'] ?? false);
        $this->line('ok: '.($ok ? 'true' : 'false'));
        $this->line('message: '.(string) ($result['message'] ?? ''));
        if (isset($result['http_status'])) {
            $this->line('http_status: '.(string) $result['http_status']);
        }
        $this->line('data: '.json_encode($result['data'] ?? null, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
