<?php

namespace App\Services\Consumer;

use App\Mail\VirtualCardReadyMail;
use App\Mail\VirtualCardTransactionMail;
use App\Models\VirtualCardRequest;
use App\Models\WhatsappSession;
use App\Models\WhatsappWallet;
use App\Services\Whatsapp\EvolutionWhatsAppClient;
use App\Services\Whatsapp\WhatsappWalletAppLinkCopy;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class VirtualCardNotificationService
{
    public function __construct(
        private EvolutionWhatsAppClient $whatsapp,
    ) {}

    public function notifyCardReadyIfNeeded(WhatsappWallet $wallet, VirtualCardRequest $card): void
    {
        $payload = is_array($card->last_operation_payload) ? $card->last_operation_payload : [];
        if (! empty($payload['created_notified_at'])) {
            return;
        }

        $wallet = $wallet->fresh();
        $card = $card->fresh();
        $cardName = trim((string) ($card->card_name ?? '')) ?: 'Your card';
        $balanceUsd = $card->card_balance_usd !== null ? (float) $card->card_balance_usd : null;

        $this->sendCardReadyNotifications($wallet, $cardName, $balanceUsd);

        $card->update([
            'last_operation_payload' => array_merge($payload, [
                'created_notified_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    /**
     * @param  array{type?: string, label?: string, amount_usd?: float|null, amount_ngn?: float|null, direction?: string, status?: string|null, reference?: string|null, created_at?: string|null}  $event
     */
    public function notifyTransaction(WhatsappWallet $wallet, VirtualCardRequest $card, array $event): void
    {
        $wallet = $wallet->fresh();
        $label = trim((string) ($event['label'] ?? 'Card activity'));
        $amountUsd = is_numeric($event['amount_usd'] ?? null) ? round((float) $event['amount_usd'], 2) : null;
        $amountNgn = is_numeric($event['amount_ngn'] ?? null) ? round((float) $event['amount_ngn'], 2) : null;
        $direction = strtolower(trim((string) ($event['direction'] ?? 'debit')));
        $status = strtolower(trim((string) ($event['status'] ?? 'success')));
        $reference = trim((string) ($event['reference'] ?? ''));
        $when = $this->formatWhen($event['created_at'] ?? null);

        $summary = $this->buildSummaryLine($label, $amountUsd, $amountNgn, $direction);
        $headline = $this->headlineForStatus($label, $status);

        if ($wallet->wantsCardTransactionWhatsapp()) {
            $this->sendWhatsapp(
                $wallet,
                $this->buildWhatsappTransactionText($headline, $summary, $when, $status, $reference),
            );
        }

        if ($wallet->wantsCardTransactionEmail()) {
            $email = $wallet->resolveOtpEmail();
            if ($email !== null) {
                try {
                    Mail::to($email)->send(new VirtualCardTransactionMail(
                        brandName: $this->brandName(),
                        headline: $headline,
                        summaryLine: $summary,
                        whenLine: $when,
                        statusLine: $status !== '' ? ucfirst($status) : null,
                        referenceLine: $reference !== '' ? $reference : null,
                    ));
                } catch (\Throwable $e) {
                    Log::warning('consumer.virtual_card.transaction_email_failed', [
                        'wallet_id' => $wallet->id,
                        'virtual_card_request_id' => $card->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    private function sendCardReadyNotifications(WhatsappWallet $wallet, string $cardName, ?float $balanceUsd): void
    {
        if ($wallet->wantsCardCreatedWhatsapp()) {
            $balanceLine = $balanceUsd !== null
                ? 'Starting balance: *$'.number_format($balanceUsd, 2)."*\n"
                : '';
            $text = "💳 *Your Dollar Virtual Card is ready*\n\n".
                "*Name:* {$cardName}\n".
                $balanceLine.
                "\nOpen the app to fund, view details, or freeze your card when not in use.".
                WhatsappWalletAppLinkCopy::downloadBlock();
            $this->sendWhatsapp($wallet, $text);
        }

        if ($wallet->wantsCardCreatedEmail()) {
            $email = $wallet->resolveOtpEmail();
            if ($email !== null) {
                try {
                    Mail::to($email)->send(new VirtualCardReadyMail(
                        brandName: $this->brandName(),
                        cardName: $cardName,
                        balanceUsd: $balanceUsd,
                    ));
                } catch (\Throwable $e) {
                    Log::warning('consumer.virtual_card.ready_email_failed', [
                        'wallet_id' => $wallet->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    private function sendWhatsapp(WhatsappWallet $wallet, string $text): void
    {
        $instance = WhatsappSession::query()
            ->where('phone_e164', $wallet->phone_e164)
            ->value('evolution_instance');

        if ($instance === null || $instance === '') {
            $instance = (string) config('whatsapp.evolution.instance', '');
        }

        if ($instance === '') {
            Log::debug('consumer.virtual_card.notify: no evolution instance', [
                'wallet_id' => $wallet->id,
            ]);

            return;
        }

        $this->whatsapp->sendText($instance, $wallet->phone_e164, $text);
    }

    private function buildWhatsappTransactionText(
        string $headline,
        string $summary,
        string $when,
        string $status,
        string $reference,
    ): string {
        $statusLine = $status !== '' && $status !== 'success'
            ? "*Status:* ".ucfirst($status)."\n"
            : '';
        $refLine = $reference !== '' ? "*Ref:* {$reference}\n" : '';

        return "💳 *{$headline}*\n\n".
            "{$summary}\n".
            "*Time:* {$when}\n".
            $statusLine.
            $refLine.
            "\n*WALLET* — balance · open the app for card history".
            WhatsappWalletAppLinkCopy::downloadBlock();
    }

    private function buildSummaryLine(string $label, ?float $amountUsd, ?float $amountNgn, string $direction): string
    {
        $sign = $direction === 'credit' ? '+' : '−';
        $parts = [$label];

        if ($amountUsd !== null && $amountUsd > 0) {
            $parts[] = "{$sign}\$".number_format($amountUsd, 2);
        }
        if ($amountNgn !== null && $amountNgn > 0) {
            $parts[] = '(≈ ₦'.number_format($amountNgn, 2).')';
        }

        return implode(' — ', array_filter($parts));
    }

    private function headlineForStatus(string $label, string $status): string
    {
        if (in_array($status, ['failed', 'declined', 'failure', 'fail', 'unsuccessful', 'rejected'], true)) {
            return 'Declined card payment';
        }

        return $label;
    }

    private function formatWhen(mixed $value): string
    {
        try {
            return Carbon::parse($value ?? now())
                ->timezone(config('app.timezone'))
                ->format('M j, Y \a\t g:i A');
        } catch (\Throwable) {
            return now()->timezone(config('app.timezone'))->format('M j, Y \a\t g:i A');
        }
    }

    private function brandName(): string
    {
        return trim((string) config('app.consumer_brand_name', config('app.name', 'CheckoutNow')));
    }
}
