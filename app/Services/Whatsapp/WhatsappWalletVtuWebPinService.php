<?php

namespace App\Services\Whatsapp;

use App\Models\Renter;
use App\Models\WhatsappSession;
use App\Models\WhatsappWallet;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * One-time web link to enter wallet PIN for VTU (airtime / data / electricity) purchases.
 */
class WhatsappWalletVtuWebPinService
{
    private const CACHE_PREFIX = 'wa_wallet_vtu_confirm:';

    public function __construct(
        private EvolutionWhatsAppClient $client,
        private WhatsappWalletVtuPurchaseService $purchase,
        private WhatsappWalletTransferCompletionService $transferCompletion,
    ) {}

    private function cacheKey(string $token): string
    {
        return self::CACHE_PREFIX.$token;
    }

    private function ttlSeconds(): int
    {
        return max(300, (int) config('whatsapp.wallet.transfer_confirm_ttl_minutes', 15) * 60);
    }

    public function confirmUrl(string $token): string
    {
        $b = rtrim((string) config('whatsapp.public_url', ''), '/');
        if ($b === '') {
            $b = rtrim((string) config('app.url'), '/');
        }

        return $b.'/wallet/whatsapp/vtu-confirm/'.$token;
    }

    public function forgetToken(?string $token): void
    {
        if ($token !== null && $token !== '') {
            Cache::forget($this->cacheKey($token));
        }
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  'airtime'|'data'|'electricity'  $kind
     */
    public function beginWebPinConfirmation(
        WhatsappSession $session,
        string $instance,
        string $phone,
        WhatsappWallet $wallet,
        array $ctx,
        string $kind,
        string $pinWebStep,
    ): void {
        $payload = $this->buildPurchasePayload($ctx, $kind, $phone);
        if ($payload === null) {
            $this->client->sendText($instance, $phone, 'Something went wrong starting payment. *BACK* and try again.');

            return;
        }

        $token = bin2hex(random_bytes(32));
        Cache::put($this->cacheKey($token), [
            'whatsapp_session_id' => $session->id,
            'phone_e164' => $phone,
            'evolution_instance' => $instance,
            'wallet_id' => $wallet->id,
            'kind' => $kind,
            'payload' => $payload,
        ], now()->addSeconds($this->ttlSeconds()));

        $ctx['step'] = $pinWebStep;
        $ctx['vtu_wallet_confirm_token'] = $token;
        unset($ctx['vtu_plans']);
        $session->update(['chat_context' => $ctx]);

        $url = $this->confirmUrl($token);
        $this->client->sendText(
            $instance,
            $phone,
            "*Confirm VTU payment*\n\n".
            "Open this link to enter your *wallet PIN* on a secure page:\n{$url}\n\n".
            "*Do not* send your PIN in this chat.\n\n".
            'Lost the link? Reply *LINK*. *BACK* — previous step · *CANCEL* — wallet'
        );
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>|null
     */
    private function buildPurchasePayload(array $ctx, string $kind, string $payerE164): ?array
    {
        if ($kind === 'airtime') {
            $net = (string) ($ctx['vtu_network'] ?? '');
            $recipient = (string) ($ctx['vtu_recipient_e164'] ?? '');
            $amount = isset($ctx['vtu_amount']) ? (float) $ctx['vtu_amount'] : 0.0;
            if ($net === '' || $recipient === '' || $amount < 1) {
                return null;
            }

            return [
                'vtu_network' => $net,
                'vtu_recipient_e164' => $recipient,
                'vtu_amount' => $amount,
            ];
        }

        if ($kind === 'data') {
            $net = (string) ($ctx['vtu_network'] ?? '');
            $recipient = (string) ($ctx['vtu_recipient_e164'] ?? '');
            $vid = (int) ($ctx['vtu_sel_variation_id'] ?? 0);
            $price = (float) ($ctx['vtu_sel_price'] ?? 0);
            if ($net === '' || $recipient === '' || $vid < 1 || $price < 1) {
                return null;
            }

            return [
                'vtu_network' => $net,
                'vtu_recipient_e164' => $recipient,
                'vtu_sel_variation_id' => $vid,
                'vtu_sel_price' => $price,
                'vtu_sel_label' => (string) ($ctx['vtu_sel_label'] ?? ''),
            ];
        }

        if ($kind === 'electricity') {
            $service = (string) ($ctx['vtu_el_service'] ?? '');
            $meter = (string) ($ctx['vtu_el_meter'] ?? '');
            $variation = (string) ($ctx['vtu_el_variation'] ?? '');
            $amount = isset($ctx['vtu_amount']) ? (float) $ctx['vtu_amount'] : 0.0;
            $cust = (string) ($ctx['vtu_el_customer_name'] ?? '');
            if ($service === '' || $meter === '' || $variation === '' || $amount < 1) {
                return null;
            }

            return [
                'vtu_el_service' => $service,
                'vtu_el_meter' => $meter,
                'vtu_el_variation' => $variation,
                'vtu_amount' => $amount,
                'vtu_el_customer_name' => $cust,
                'payer_e164' => $payerE164,
            ];
        }

        return null;
    }

    /**
     * @return array{ok: bool, summary?: string, error?: string}
     */
    public function describePending(string $token): array
    {
        $row = Cache::get($this->cacheKey($token));
        if (! is_array($row)) {
            return ['ok' => false, 'error' => 'This link has expired or was already used.'];
        }

        $kind = (string) ($row['kind'] ?? '');
        $p = $row['payload'] ?? [];
        if (! is_array($p) || ! in_array($kind, ['airtime', 'data', 'electricity'], true)) {
            return ['ok' => false, 'error' => 'Invalid link.'];
        }

        return ['ok' => true, 'summary' => $this->summarize($kind, $p)];
    }

    /**
     * @param  array<string, mixed>  $p
     */
    private function summarize(string $kind, array $p): string
    {
        if ($kind === 'airtime') {
            $amt = number_format((float) ($p['vtu_amount'] ?? 0), 2);
            $mask = $this->maskPhone((string) ($p['vtu_recipient_e164'] ?? ''));
            $netLabel = $this->networkLabel((string) ($p['vtu_network'] ?? ''));

            return "Airtime ₦{$amt} → {$mask} ({$netLabel})";
        }

        if ($kind === 'data') {
            $price = number_format((float) ($p['vtu_sel_price'] ?? 0), 2);
            $label = trim((string) ($p['vtu_sel_label'] ?? ''));
            $mask = $this->maskPhone((string) ($p['vtu_recipient_e164'] ?? ''));

            return $label !== '' ? "{$label} — ₦{$price} → {$mask}" : "Data ₦{$price} → {$mask}";
        }

        $amt = number_format((float) ($p['vtu_amount'] ?? 0), 2);
        $meter = (string) ($p['vtu_el_meter'] ?? '');
        $tail = strlen($meter) >= 4 ? '••••'.substr($meter, -4) : '••••';
        $name = trim((string) ($p['vtu_el_customer_name'] ?? ''));

        return "Electricity ₦{$amt} — meter {$tail}".($name !== '' ? " ({$name})" : '');
    }

    private function networkLabel(string $networkId): string
    {
        $nets = config('vtu.networks', []);
        if (! is_array($nets)) {
            return $networkId;
        }
        foreach ($nets as $n) {
            if (is_array($n) && (string) ($n['id'] ?? '') === $networkId) {
                return (string) ($n['label'] ?? $networkId);
            }
        }

        return $networkId;
    }

    private function maskPhone(string $e164): string
    {
        $d = preg_replace('/\D/', '', $e164) ?? '';
        if (strlen($d) < 9) {
            return $e164;
        }

        return substr($d, 0, 5).' •••• '.substr($d, -4);
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function confirmViaWebPin(string $token, string $pinDigits): array
    {
        $row = Cache::get($this->cacheKey($token));
        if (! is_array($row)) {
            return ['ok' => false, 'error' => 'This link has expired or was already used. Start again in WhatsApp.'];
        }

        $sessionId = (int) ($row['whatsapp_session_id'] ?? 0);
        $phone = (string) ($row['phone_e164'] ?? '');
        $instance = (string) ($row['evolution_instance'] ?? '');
        $walletId = (int) ($row['wallet_id'] ?? 0);
        $kind = (string) ($row['kind'] ?? '');
        $payload = $row['payload'] ?? [];

        if ($sessionId < 1 || $phone === '' || $instance === '' || $walletId < 1
            || ! in_array($kind, ['airtime', 'data', 'electricity'], true) || ! is_array($payload)) {
            return ['ok' => false, 'error' => 'Invalid confirmation data.'];
        }

        $session = WhatsappSession::query()->find($sessionId);
        $wallet = WhatsappWallet::query()->find($walletId);
        if (! $session || ! $wallet || (string) $session->phone_e164 !== $phone || (string) $wallet->phone_e164 !== $phone) {
            return ['ok' => false, 'error' => 'Session no longer valid.'];
        }

        if ($wallet->isPinLocked()) {
            return ['ok' => false, 'error' => 'Wallet PIN is locked. Try again later in WhatsApp.'];
        }

        if (! $wallet->pin_hash || ! Hash::check($pinDigits, (string) $wallet->pin_hash)) {
            $wallet->increment('pin_failed_attempts');
            $wallet->refresh();
            if ((int) $wallet->pin_failed_attempts >= 5) {
                $wallet->pin_locked_until = now()->addMinutes(15);
                $wallet->save();
                Cache::forget($this->cacheKey($token));

                return ['ok' => false, 'error' => 'Too many wrong PIN attempts. Wallet PIN locked for 15 minutes.'];
            }

            return ['ok' => false, 'error' => 'Incorrect wallet PIN.'];
        }

        $wallet->pin_failed_attempts = 0;
        $wallet->pin_locked_until = null;
        $wallet->save();

        Cache::forget($this->cacheKey($token));

        $session->update([
            'chat_flow' => WhatsappWaWalletMenuHandler::FLOW,
            'chat_context' => ['step' => 'submenu'],
        ]);
        $session = $session->fresh();
        $wallet = $wallet->fresh();

        $linkedRenter = Renter::query()
            ->where('whatsapp_phone_e164', $phone)
            ->where('is_active', true)
            ->first();

        $result = match ($kind) {
            'airtime' => $this->purchase->purchaseAirtime(
                $wallet,
                (string) $payload['vtu_network'],
                (string) $payload['vtu_recipient_e164'],
                (float) $payload['vtu_amount']
            ),
            'data' => $this->purchase->purchaseData(
                $wallet,
                (string) $payload['vtu_network'],
                (string) $payload['vtu_recipient_e164'],
                (int) $payload['vtu_sel_variation_id'],
                (float) $payload['vtu_sel_price']
            ),
            'electricity' => $this->purchase->purchaseElectricity(
                $wallet,
                (string) $payload['vtu_el_service'],
                (string) $payload['vtu_el_meter'],
                (string) $payload['vtu_el_variation'],
                (string) $payload['payer_e164'],
                (float) $payload['vtu_amount'],
                ($n = trim((string) ($payload['vtu_el_customer_name'] ?? ''))) !== '' ? $n : null
            ),
            default => ['ok' => false, 'message' => 'Unknown purchase type.'],
        };

        $w = $wallet->fresh();
        if ($result['ok'] ?? false) {
            $w->pin_failed_attempts = 0;
            $w->save();
            $bal = isset($result['balance_after']) ? (float) $result['balance_after'] : (float) $w->balance;
            $this->client->sendText(
                $instance,
                $phone,
                "*Done* ✅\n\n".
                ($result['message'] ?? 'Success')."\n\n".
                '💰 New balance: *₦'.number_format($bal, 2).'*'
            );
        } else {
            $this->client->sendText(
                $instance,
                $phone,
                ($result['message'] ?? 'Purchase failed.')."\n\nOpen *WALLET* to try again."
            );
        }

        $this->transferCompletion->sendWalletSubmenu($instance, $phone, $this->findOrCreateWallet($phone, $linkedRenter)->fresh());

        return ['ok' => true];
    }

    private function findOrCreateWallet(string $phone, ?Renter $renter): WhatsappWallet
    {
        $w = WhatsappWallet::query()->firstOrCreate(
            ['phone_e164' => $phone],
            [
                'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
                'balance' => 0,
                'status' => WhatsappWallet::STATUS_ACTIVE,
            ]
        );
        if ($renter && $w->renter_id === null) {
            $w->renter_id = $renter->id;
            $w->save();
        }

        return $w->fresh();
    }
}
