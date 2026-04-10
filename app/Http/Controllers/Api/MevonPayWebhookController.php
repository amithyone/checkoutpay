<?php

namespace App\Http\Controllers\Api;

use App\Events\PaymentApproved;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Whatsapp\WhatsappWalletTier1TopupVaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MevonPayWebhookController extends Controller
{
    private const WEBHOOK_SOURCE_CACHE_KEY = 'mevonpay:webhook:recent_sources';
    private const WEBHOOK_SOURCE_CACHE_LIMIT = 200;

    public function receive(Request $request): JsonResponse
    {
        $this->recordWebhookSource($request, 'received');

        if (! $this->isAllowedSender($request)) {
            $this->recordWebhookSource($request, 'blocked_allowlist');
            Log::warning('MEVONPAY webhook blocked by allowlist guard', [
                'ip' => $request->ip(),
                'origin' => (string) $request->header('Origin', ''),
                'referer' => (string) $request->header('Referer', ''),
            ]);

            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $secret = (string) config('services.mevonpay.webhook_secret', '');
        if ($secret !== '') {
            $token = (string) preg_replace('/^Bearer\s+/i', '', (string) $request->header('Authorization', ''));
            if (!hash_equals($secret, $token)) {
                $this->recordWebhookSource($request, 'blocked_secret');
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }
        }

        $payload = $request->all();
        $event = (string) data_get($payload, 'event', '');
        if ($event !== 'funding.success') {
            $this->recordWebhookSource($request, 'ignored_event');
            return response()->json(['success' => true, 'message' => 'Ignored']);
        }

        $accountNumber = trim((string) data_get($payload, 'data.account_number', ''));
        $amount = (float) data_get($payload, 'data.amount', 0);
        $reference = (string) data_get($payload, 'data.reference', '');

        if ($accountNumber === '') {
            $this->recordWebhookSource($request, 'invalid_payload');
            return response()->json(['success' => false, 'message' => 'Invalid payload'], 422);
        }

        $waTopup = app(WhatsappWalletTier1TopupVaService::class);

        if ($amount <= 0) {
            if ($waTopup->tryLogZeroAmountWebhook($accountNumber, $reference, $payload)) {
                $this->recordWebhookSource($request, 'whatsapp_wallet_topup_no_amount');

                return response()->json(['success' => true, 'message' => 'WhatsApp wallet webhook logged (no amount)']);
            }

            $this->recordWebhookSource($request, 'invalid_payload');
            return response()->json(['success' => false, 'message' => 'Invalid payload'], 422);
        }

        $webhookMeta = [
            'sender' => (string) data_get($payload, 'data.sender', ''),
            'bank_name' => (string) data_get($payload, 'data.bank_name', ''),
        ];

        $payment = Payment::where('status', Payment::STATUS_PENDING)
            ->whereIn('payment_source', [
                Payment::SOURCE_EXTERNAL_MEVONPAY,
                Payment::SOURCE_EXTERNAL_SLA,
                Payment::SOURCE_EXTERNAL_MAVONPAY,
            ])
            ->where('account_number', $accountNumber)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderBy('created_at')
            ->first();

        if (! $payment) {
            if ($waTopup->tryFulfillFromWebhook($accountNumber, $amount, $reference, $webhookMeta)) {
                $this->recordWebhookSource($request, 'whatsapp_wallet_topup');

                return response()->json(['success' => true, 'message' => 'WhatsApp wallet top-up applied']);
            }

            if ($waTopup->tryFulfillPermanentVaFromWebhook($accountNumber, $amount, $reference, $webhookMeta)) {
                $this->recordWebhookSource($request, 'whatsapp_wallet_permanent_va_topup');

                return response()->json(['success' => true, 'message' => 'WhatsApp wallet top-up applied (permanent VA)']);
            }

            $this->recordWebhookSource($request, 'no_payment');
            Log::warning('MEVONPAY webhook could not find pending payment by account number', [
                'account_number' => $accountNumber,
                'reference' => $reference,
            ]);

            return response()->json(['success' => true, 'message' => 'No pending payment']);
        }

        $payment->approve([
            'source' => 'mevonpay_webhook',
            'reference' => $reference,
            'account_number' => $accountNumber,
            'amount' => $amount,
            'bank' => (string) data_get($payload, 'data.bank_name', ''),
            'payer_name' => (string) data_get($payload, 'data.sender', ''),
            'timestamp' => (string) data_get($payload, 'data.timestamp', now()->toISOString()),
        ], false, $amount, null);

        $payment->update([
            'payment_source' => Payment::SOURCE_EXTERNAL_MEVONPAY,
            'external_reference' => $reference !== '' ? $reference : null,
        ]);

        if ($payment->business_id) {
            $payment->business->incrementBalanceWithCharges($payment->amount, $payment, $amount);
            $payment->business->refresh();
            $payment->business->notify(new \App\Notifications\NewDepositNotification($payment));
            $payment->business->triggerAutoWithdrawal();
        }

        $payment->refresh();
        $payment->load(['business.websites', 'website']);
        event(new PaymentApproved($payment));
        $this->recordWebhookSource($request, 'processed');

        return response()->json(['success' => true]);
    }

    private function recordWebhookSource(Request $request, string $status): void
    {
        $entries = Cache::get(self::WEBHOOK_SOURCE_CACHE_KEY, []);
        if (!is_array($entries)) {
            $entries = [];
        }

        $entries[] = [
            'received_at' => now()->toDateTimeString(),
            'status' => $status,
            'ip' => (string) $request->ip(),
            'origin' => (string) $request->header('Origin', ''),
            'referer' => (string) $request->header('Referer', ''),
            'forwarded_for' => (string) $request->header('X-Forwarded-For', ''),
            'forwarded_host' => (string) $request->header('X-Forwarded-Host', ''),
            'user_agent' => (string) $request->userAgent(),
        ];

        if (count($entries) > self::WEBHOOK_SOURCE_CACHE_LIMIT) {
            $entries = array_slice($entries, -1 * self::WEBHOOK_SOURCE_CACHE_LIMIT);
        }

        Cache::put(self::WEBHOOK_SOURCE_CACHE_KEY, $entries, now()->addDays(7));
    }

    private function isAllowedSender(Request $request): bool
    {
        $allowedIps = (array) config('services.mevonpay.webhook_allowed_ips', []);
        $allowedDomains = (array) config('services.mevonpay.webhook_allowed_domains', []);

        if (empty($allowedIps) && empty($allowedDomains)) {
            return true;
        }

        if (!empty($allowedIps) && !in_array((string) $request->ip(), $allowedIps, true)) {
            return false;
        }

        if (empty($allowedDomains)) {
            return true;
        }

        $originHost = (string) parse_url((string) $request->header('Origin', ''), PHP_URL_HOST);
        $refererHost = (string) parse_url((string) $request->header('Referer', ''), PHP_URL_HOST);
        $forwardedHost = (string) $request->header('X-Forwarded-Host', '');

        $candidateHosts = collect([$originHost, $refererHost, $forwardedHost])
            ->filter()
            ->map(fn ($host) => Str::lower(trim((string) $host)))
            ->unique()
            ->values()
            ->all();

        if (empty($candidateHosts)) {
            // If domain guard is configured but sender does not provide host metadata, block.
            return false;
        }

        foreach ($candidateHosts as $host) {
            foreach ($allowedDomains as $allowedDomain) {
                $domain = Str::lower(trim((string) $allowedDomain));
                if ($domain !== '' && ($host === $domain || Str::endsWith($host, '.'.$domain))) {
                    return true;
                }
            }
        }

        return false;
    }
}
