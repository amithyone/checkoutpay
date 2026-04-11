<?php

namespace App\Services\Whatsapp;

use App\Mail\WhatsappWalletTransferOtpMail;
use App\Models\WhatsappSession;
use App\Models\WhatsappWallet;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Email OTP + secure web PIN link for WhatsApp wallet transfers (bank / P2P).
 */
class WhatsappWalletSecureTransferAuthService
{
    private const CACHE_PREFIX = 'wa_wallet_transfer_confirm:';

    public function __construct(
        private EvolutionWhatsAppClient $client,
        private WhatsappWalletTransferCompletionService $completion,
    ) {}

    private function cacheKey(string $token): string
    {
        return self::CACHE_PREFIX.$token;
    }

    private function linkTtlSeconds(): int
    {
        return max(300, (int) config('whatsapp.wallet.transfer_confirm_ttl_minutes', 15) * 60);
    }

    private function otpTtlMinutes(): int
    {
        return max(3, (int) config('whatsapp.otp.ttl_minutes', 10));
    }

    private function maxOtpAttempts(): int
    {
        return max(3, (int) config('whatsapp.otp.max_attempts', 5));
    }

    public function secureConfirmBaseUrl(): string
    {
        $b = rtrim((string) config('whatsapp.public_url', ''), '/');
        if ($b === '') {
            $b = rtrim((string) config('app.url'), '/');
        }

        return $b;
    }

    /**
     * Send the URL alone in a follow-up message so WhatsApp parses tappable links reliably
     * (domains with hyphens often break when the URL sits inside a formatted block).
     */
    private function sendStandaloneConfirmLink(string $instance, string $phone, string $linkUrl): void
    {
        $this->client->sendText($instance, $phone, $linkUrl);
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    private function executionContextFromTransferCtx(array $ctx): array
    {
        unset(
            $ctx['step'],
            $ctx['wallet_transfer_otp_hash'],
            $ctx['wallet_transfer_otp_expires_at'],
            $ctx['wallet_transfer_otp_attempts'],
            $ctx['wallet_transfer_confirm_token'],
        );

        return $ctx;
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    public function beginBankTransferConfirmation(
        WhatsappSession $session,
        string $instance,
        string $phone,
        WhatsappWallet $wallet,
        array $ctx,
    ): void {
        $this->beginConfirmation($session, $instance, $phone, $wallet, $ctx, 'bank');
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    public function beginP2pTransferConfirmation(
        WhatsappSession $session,
        string $instance,
        string $phone,
        WhatsappWallet $wallet,
        array $ctx,
    ): void {
        $this->beginConfirmation($session, $instance, $phone, $wallet, $ctx, 'p2p');
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function beginConfirmation(
        WhatsappSession $session,
        string $instance,
        string $phone,
        WhatsappWallet $wallet,
        array $ctx,
        string $kind,
    ): void {
        $token = bin2hex(random_bytes(32));
        $execCtx = $this->executionContextFromTransferCtx($ctx);
        Cache::put($this->cacheKey($token), [
            'whatsapp_session_id' => $session->id,
            'phone_e164' => $phone,
            'evolution_instance' => $instance,
            'wallet_id' => $wallet->id,
            'kind' => $kind,
            'ctx' => $execCtx,
        ], now()->addSeconds($this->linkTtlSeconds()));

        $email = $wallet->resolveOtpEmail();
        $linkUrl = $this->secureConfirmBaseUrl().'/wallet/whatsapp/confirm/'.$token;
        $brand = (string) config('whatsapp.bot_brand_name', 'CheckoutNow');
        $summaryLine = $kind === 'bank'
            ? $this->summarizeBank($execCtx)
            : $this->summarizeP2p($execCtx);

        if (! $email) {
            $ctx['step'] = $kind === 'bank' ? 'transfer_pin' : 'p2p_pin';
            $ctx['wallet_transfer_confirm_token'] = $token;
            $session->update(['chat_context' => $ctx]);
            $this->client->sendText(
                $instance,
                $phone,
                "*No email on file* for a login code.\n\n".
                "You can enter your *4-digit wallet PIN* here, *or* tap the link in the *next message* to type your PIN on a secure page (safer).\n\n".
                '*BACK* — cancel'
            );
            $this->sendStandaloneConfirmLink($instance, $phone, $linkUrl);

            return;
        }

        $code = (string) random_int(100000, 999999);
        $otpExpires = now()->addMinutes($this->otpTtlMinutes());

        try {
            Mail::to($email)->send(new WhatsappWalletTransferOtpMail(
                $code,
                $this->otpTtlMinutes(),
                (int) ceil($this->linkTtlSeconds() / 60),
                $summaryLine,
                $linkUrl,
                $brand,
            ));
        } catch (\Throwable $e) {
            Log::error('whatsapp.wallet.transfer_otp_mail_failed', ['error' => $e->getMessage(), 'wallet_id' => $wallet->id]);
            $ctx['step'] = $kind === 'bank' ? 'transfer_pin' : 'p2p_pin';
            $ctx['wallet_transfer_confirm_token'] = $token;
            $session->update(['chat_context' => $ctx]);
            $this->client->sendText(
                $instance,
                $phone,
                "We could not send email right now.\n\n".
                "Enter your *4-digit wallet PIN* here, *or* tap the link in the *next message* to type your PIN on a secure page.\n\n".
                '*BACK* — cancel'
            );
            $this->sendStandaloneConfirmLink($instance, $phone, $linkUrl);

            return;
        }

        $ctx['wallet_transfer_otp_hash'] = Hash::make($code);
        $ctx['wallet_transfer_otp_expires_at'] = $otpExpires->timestamp;
        $ctx['wallet_transfer_otp_attempts'] = 0;
        $ctx['wallet_transfer_confirm_token'] = $token;
        $ctx['step'] = $kind === 'bank' ? 'transfer_otp' : 'p2p_otp';
        $session->update(['chat_context' => $ctx]);

        $masked = $this->maskEmail($email);
        $this->client->sendText(
            $instance,
            $phone,
            "*Check your email* ({$masked})\n\n".
            "We sent a *6-digit code*. Send that code *here* in WhatsApp — *do not* send your wallet PIN in this chat.\n\n".
            "Or open the link in the email to enter your PIN on a secure page.\n\n".
            '*BACK* — cancel'
        );
    }

    private function maskEmail(string $email): string
    {
        if (! str_contains($email, '@')) {
            return '***';
        }
        [$local, $domain] = explode('@', $email, 2);
        $keep = max(2, min(4, strlen($local)));

        return substr($local, 0, $keep).'•••@'.$domain;
    }

    /**
     * @param  array<string, mixed>  $execCtx
     */
    private function summarizeBank(array $execCtx): string
    {
        $amount = isset($execCtx['amount']) && is_numeric($execCtx['amount']) ? (float) $execCtx['amount'] : 0.0;
        $bank = isset($execCtx['dest_bank']) && is_string($execCtx['dest_bank']) ? $execCtx['dest_bank'] : '';
        $acct = isset($execCtx['dest_acct']) && is_string($execCtx['dest_acct']) ? $execCtx['dest_acct'] : '';

        return 'Bank transfer: ₦'.number_format($amount, 2).' → '.$bank.' / '.$acct;
    }

    /**
     * @param  array<string, mixed>  $execCtx
     */
    private function summarizeP2p(array $execCtx): string
    {
        $amount = isset($execCtx['p2p_amount']) && is_numeric($execCtx['p2p_amount']) ? (float) $execCtx['p2p_amount'] : 0.0;
        $to = isset($execCtx['p2p_recipient_e164']) && is_string($execCtx['p2p_recipient_e164']) ? $execCtx['p2p_recipient_e164'] : '';
        $suffix = ! empty($execCtx['p2p_recipient_unregistered'])
            ? ' (recipient must open WALLET to claim; auto-refund if not)'
            : '';

        return 'WhatsApp send: ₦'.number_format($amount, 2).' → '.$to.$suffix;
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    public function verifyBankTransferOtp(
        WhatsappSession $session,
        string $instance,
        string $phone,
        WhatsappWallet $wallet,
        array $ctx,
        string $otpDigits,
    ): void {
        $this->verifyOtpAndComplete($session, $instance, $phone, $wallet, $ctx, $otpDigits, 'bank');
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    public function verifyP2pTransferOtp(
        WhatsappSession $session,
        string $instance,
        string $phone,
        WhatsappWallet $wallet,
        array $ctx,
        string $otpDigits,
    ): void {
        $this->verifyOtpAndComplete($session, $instance, $phone, $wallet, $ctx, $otpDigits, 'p2p');
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function verifyOtpAndComplete(
        WhatsappSession $session,
        string $instance,
        string $phone,
        WhatsappWallet $wallet,
        array $ctx,
        string $otpDigits,
        string $kind,
    ): void {
        $hash = $ctx['wallet_transfer_otp_hash'] ?? null;
        $exp = isset($ctx['wallet_transfer_otp_expires_at']) ? (int) $ctx['wallet_transfer_otp_expires_at'] : 0;
        if (! is_string($hash) || $hash === '') {
            $this->client->sendText($instance, $phone, 'Start the transfer again from *2* or *4*.');

            return;
        }
        if ($exp < now()->timestamp) {
            $this->invalidateConfirm($session, $ctx);
            $this->client->sendText($instance, $phone, 'That code has expired. Start the transfer again.');

            return;
        }

        $attempts = (int) ($ctx['wallet_transfer_otp_attempts'] ?? 0);
        if ($attempts >= $this->maxOtpAttempts()) {
            $this->invalidateConfirm($session, $ctx);
            $this->client->sendText($instance, $phone, 'Too many failed codes. Start the transfer again.');

            return;
        }

        if (! Hash::check($otpDigits, $hash)) {
            $ctx['wallet_transfer_otp_attempts'] = $attempts + 1;
            $session->update(['chat_context' => $ctx]);
            $this->client->sendText($instance, $phone, 'Wrong code. Check your email or use the link we sent.');

            return;
        }

        $execCtx = $this->executionContextFromTransferCtx($ctx);
        $token = isset($ctx['wallet_transfer_confirm_token']) && is_string($ctx['wallet_transfer_confirm_token'])
            ? $ctx['wallet_transfer_confirm_token']
            : '';
        if ($token !== '') {
            Cache::forget($this->cacheKey($token));
        }

        if ($kind === 'bank') {
            $this->completion->completeBankTransfer($session->fresh(), $instance, $phone, $wallet->fresh(), $execCtx, false);
        } else {
            $this->completion->completeP2pTransfer($session->fresh(), $instance, $phone, $wallet->fresh(), $execCtx, false);
        }
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function invalidateConfirm(WhatsappSession $session, array $ctx): void
    {
        $token = isset($ctx['wallet_transfer_confirm_token']) && is_string($ctx['wallet_transfer_confirm_token'])
            ? $ctx['wallet_transfer_confirm_token']
            : '';
        if ($token !== '') {
            Cache::forget($this->cacheKey($token));
        }
        $session->update(['chat_context' => ['step' => 'submenu']]);
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function confirmViaWebPin(string $token, string $pinDigits): array
    {
        $payload = Cache::get($this->cacheKey($token));
        if (! is_array($payload)) {
            return ['ok' => false, 'error' => 'This link has expired or was already used. Start again in WhatsApp.'];
        }

        $sessionId = (int) ($payload['whatsapp_session_id'] ?? 0);
        $phone = (string) ($payload['phone_e164'] ?? '');
        $instance = (string) ($payload['evolution_instance'] ?? '');
        $walletId = (int) ($payload['wallet_id'] ?? 0);
        $kind = (string) ($payload['kind'] ?? '');
        $execCtx = $payload['ctx'] ?? [];
        if ($sessionId < 1 || $phone === '' || $instance === '' || $walletId < 1 || ! in_array($kind, ['bank', 'p2p'], true) || ! is_array($execCtx)) {
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
            return ['ok' => false, 'error' => 'Incorrect wallet PIN.'];
        }

        Cache::forget($this->cacheKey($token));
        $session->update(['chat_context' => ['step' => 'submenu']]);
        $session = $session->fresh();
        $wallet = $wallet->fresh();

        if ($kind === 'bank') {
            $this->completion->completeBankTransfer($session, $instance, $phone, $wallet, $execCtx, false);
        } else {
            $this->completion->completeP2pTransfer($session, $instance, $phone, $wallet, $execCtx, false);
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, kind?: string, summary?: string, error?: string}
     */
    public function describePendingWebConfirm(string $token): array
    {
        $payload = Cache::get($this->cacheKey($token));
        if (! is_array($payload)) {
            return ['ok' => false, 'error' => 'This link has expired or was already used.'];
        }

        $kind = (string) ($payload['kind'] ?? '');
        $execCtx = $payload['ctx'] ?? [];
        if (! is_array($execCtx) || ! in_array($kind, ['bank', 'p2p'], true)) {
            return ['ok' => false, 'error' => 'Invalid link.'];
        }

        $summary = $kind === 'bank' ? $this->summarizeBank($execCtx) : $this->summarizeP2p($execCtx);

        return ['ok' => true, 'kind' => $kind, 'summary' => $summary];
    }

    public function forgetConfirmTokenIfPresent(?string $token): void
    {
        if ($token !== null && $token !== '') {
            Cache::forget($this->cacheKey($token));
        }
    }
}
