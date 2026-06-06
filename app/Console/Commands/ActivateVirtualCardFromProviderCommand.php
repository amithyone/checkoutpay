<?php

namespace App\Console\Commands;

use App\Models\VirtualCardRequest;
use App\Services\Consumer\VirtualCardFeeRefundService;
use App\Services\Consumer\VirtualCardProviderResponseService;
use Illuminate\Console\Command;

class ActivateVirtualCardFromProviderCommand extends Command
{
    protected $signature = 'virtual-card:activate-provider
        {card_id : MevonPay card_id from webhook}
        {--email= : Match wallet request by email in request_payload}
        {--reference= : Match by Checkout VCARD- reference or Mevon UUID in response_payload}
        {--request-id= : Exact virtual_card_requests.id}';

    protected $description = 'Manually attach a MevonPay card_id to a virtual card request (webhook recovery)';

    public function handle(
        VirtualCardProviderResponseService $providerResponse,
        VirtualCardFeeRefundService $feeRefunds,
    ): int
    {
        $cardId = trim((string) $this->argument('card_id'));
        if ($cardId === '') {
            $this->error('card_id is required.');

            return self::FAILURE;
        }

        $row = null;
        $requestId = $this->option('request-id');
        if ($requestId) {
            $row = VirtualCardRequest::query()->find($requestId);
        }

        if (! $row && $this->option('reference')) {
            $ref = trim((string) $this->option('reference'));
            $row = VirtualCardRequest::query()->where('external_reference', $ref)->first();
            if (! $row && $ref !== '') {
                $row = VirtualCardRequest::query()
                    ->where('response_payload', 'like', '%'.$ref.'%')
                    ->latest('id')
                    ->first();
            }
        }

        if (! $row && $this->option('email')) {
            $email = strtolower(trim((string) $this->option('email')));
            $row = VirtualCardRequest::query()
                ->whereIn('status', [
                    VirtualCardRequest::STATUS_PENDING,
                    VirtualCardRequest::STATUS_PREPARING,
                    VirtualCardRequest::STATUS_SUBMITTED,
                    VirtualCardRequest::STATUS_FAILED,
                ])
                ->whereNull('card_external_id')
                ->latest('id')
                ->get()
                ->first(function (VirtualCardRequest $candidate) use ($email) {
                    $payload = is_array($candidate->request_payload) ? $candidate->request_payload : [];

                    return strtolower(trim((string) ($payload['email'] ?? ''))) === $email;
                });
        }

        if (! $row) {
            $this->error('No matching virtual card request found.');

            return self::FAILURE;
        }

        $collection = $feeRefunds->ensureFeeCollectedForActivation($row);
        if (! ($collection['ok'] ?? false)) {
            $this->error((string) ($collection['message'] ?? 'Could not collect card fee before activation.'));

            return self::FAILURE;
        }

        if ($collection['collected'] ?? false) {
            $this->warn('Refunded card fee was re-debited from the wallet before activation.');
        }

        $providerResponse->applyWebhookReady($row, [
            'manual_recovery' => true,
            'card_id' => $cardId,
        ], $cardId);

        $row->refresh();
        $this->info("Activated request #{$row->id} for wallet #{$row->whatsapp_wallet_id} with card {$row->card_external_id}");

        return self::SUCCESS;
    }
}
