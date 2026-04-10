<?php

namespace App\Services\Whatsapp;

use App\Mail\WhatsappLoginOtpMail;
use App\Models\Renter;
use App\Models\WhatsappSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class WhatsappInboundHandler
{
    public function __construct(
        private EvolutionWebhookPayloadParser $parser,
        private EvolutionWhatsAppClient $client,
        private WhatsappLinkedMenuHandler $linkedMenu,
        private WhatsappGuestRentalBrowseHandler $guestRentalBrowse,
        private WhatsappCheckoutServicesMenuHandler $checkoutServicesMenu,
        private WhatsappWalletUpgradeFlowHandler $walletUpgradeFlow,
        private WhatsappWaWalletMenuHandler $waWalletMenu,
    ) {}

    public function handleRequest(Request $request): void
    {
        foreach ($this->parser->extractInboundMessages($request) as $msg) {
            try {
                $this->handleOne($msg);
            } catch (\Throwable $e) {
                Log::error('whatsapp.inbound: handleOne failed', [
                    'error' => $e->getMessage(),
                    'phone' => $msg['phone_e164'] ?? null,
                ]);
            }
        }
    }

    /**
     * @param  array{instance: string, remote_jid: string, phone_e164: string, text: string}  $msg
     */
    private function handleOne(array $msg): void
    {
        $instance = $msg['instance'] ?: (string) config('whatsapp.evolution.instance', '');
        if ($instance === '') {
            Log::warning('whatsapp.inbound: missing evolution instance name');

            return;
        }

        $phone = $msg['phone_e164'];
        $text = trim($msg['text']);
        $remoteJid = $msg['remote_jid'];
        $cmd = WhatsappMenuInputNormalizer::commandToken($text);

        $session = WhatsappSession::query()->firstOrNew(['phone_e164' => $phone]);
        $session->remote_jid = $remoteJid;
        $session->evolution_instance = $instance;

        if ($session->exists && $session->state === WhatsappSession::STATE_AWAIT_OTP) {
            $session->save();
            $this->stepVerifyOtp($session->fresh(), $instance, $phone, $text);

            return;
        }

        if ($cmd === 'STOP') {
            $session->bot_paused = true;
            $session->save();
            $this->client->sendText(
                $instance,
                $phone,
                "*Paused*\n\nAutomated replies are off.\nSend *START* or *MENU* when you want them again.\nAfter you resume, *RESTART* takes you back to the main categories."
            );

            return;
        }

        if ((bool) ($session->bot_paused ?? false)) {
            $resume = [
                'START', 'MENU', 'SERVICES', '0', 'HI', 'HELLO', 'HELP', 'HOME',
                'RESTART', 'MAIN',
                'RENTALS', 'BROWSE', 'SHOP', 'CATALOG',
                'WALLET', 'TICKET', 'TICKETS', 'SUPPORT',
                'INVOICE', 'INVOICES', 'PAY', 'PAYMENT',
                'TOPUP', 'TOP UP', 'UPGRADE', 'TIER2', 'TIER 2',
                '1', '2', '3', '4', '5',
            ];
            if (! in_array($cmd, $resume, true)) {
                $session->save();
                $this->client->sendText(
                    $instance,
                    $phone,
                    'Automated replies are *paused*. Send *START* or *MENU* to continue.'
                );

                return;
            }
            $session->bot_paused = false;
        }

        $session->save();
        $session = $session->fresh();

        $linkedRenter = Renter::query()
            ->where('whatsapp_phone_e164', $phone)
            ->where('is_active', true)
            ->first();

        if (in_array($cmd, ['RESTART', 'MAIN'], true)) {
            $session->update([
                'chat_flow' => null,
                'chat_context' => null,
            ]);
            $session = $session->fresh();
            if ($linkedRenter) {
                $this->linkedMenu->sendRootForRenter($linkedRenter->fresh(), $instance, $phone);
            } else {
                $this->checkoutServicesMenu->sendRootMenu($instance, $phone);
            }

            return;
        }

        if ($session && $session->chat_flow === WhatsappWalletUpgradeFlowHandler::FLOW) {
            $this->walletUpgradeFlow->handle($session, $instance, $phone, $text, $cmd);

            return;
        }

        if ($session && $session->chat_flow === WhatsappWaWalletMenuHandler::FLOW) {
            $this->waWalletMenu->handle($session, $instance, $phone, $text, $cmd, $linkedRenter);

            return;
        }

        if (in_array($cmd, ['UPGRADE', 'TIER2', 'TIER 2'], true)) {
            $this->walletUpgradeFlow->start($session->fresh(), $instance, $phone);

            return;
        }

        $renter = $linkedRenter;

        if ($renter) {
            $this->sendLinkedWelcome($instance, $phone, $remoteJid, $renter, $text);

            return;
        }

        $session->remote_jid = $remoteJid;
        $session->evolution_instance = $instance;

        if (
            $session->chat_flow === WhatsappGuestRentalBrowseHandler::FLOW
            && filter_var($text, FILTER_VALIDATE_EMAIL)
        ) {
            $session->update(['chat_flow' => null, 'chat_context' => null]);
            $session = $session->fresh();
        }

        if (
            $session->chat_flow === WhatsappGuestRentalBrowseHandler::FLOW
            || $this->isGuestRentalBrowseCommand($text)
        ) {
            $session->save();
            $this->guestRentalBrowse->handle($session->fresh(), $instance, $phone, $text);

            return;
        }

        if ($this->isCheckoutServicesCommand($text)) {
            $session->save();
            $this->checkoutServicesMenu->handleCommand($session->fresh(), $instance, $phone, $text);

            return;
        }

        if ($session->state === WhatsappSession::STATE_AWAIT_EMAIL) {
            $this->stepRequestEmail($session, $instance, $phone, $text);

            return;
        }

        if ($session->state === WhatsappSession::STATE_LINKED && $session->renter_id) {
            $linked = Renter::query()->find($session->renter_id);
            if ($linked && $linked->is_active && $linked->whatsapp_phone_e164 === $phone) {
                $this->sendLinkedWelcome($instance, $phone, $remoteJid, $linked, $text);

                return;
            }
            $session->update(['state' => WhatsappSession::STATE_WELCOME, 'renter_id' => null]);
        }

        $this->stepWelcomeOrEmail($session, $instance, $phone, $text);
    }

    private function isGuestRentalBrowseCommand(string $text): bool
    {
        $cmd = WhatsappMenuInputNormalizer::commandToken($text);

        return in_array($cmd, ['RENTALS', 'BROWSE', 'SHOP', 'CATALOG', '1'], true);
    }

    private function isCheckoutServicesCommand(string $text): bool
    {
        $cmd = WhatsappMenuInputNormalizer::commandToken($text);

        return in_array($cmd, [
            'MENU', 'START', 'SERVICES', '0', 'HI', 'HELLO', 'HELP', 'HOME',
            'WALLET', 'TICKET', 'TICKETS', 'SUPPORT',
            'INVOICE', 'INVOICES', 'PAY', 'PAYMENT',
            'TOPUP', 'TOP UP',
            '2', '3', '4',
        ], true);
    }

    /**
     * Finish linking after OTP in chat or magic link in the browser.
     */
    public function completeLinkAfterVerification(WhatsappSession $session, Renter $renter): void
    {
        $phone = $session->phone_e164;
        $instance = $session->evolution_instance;

        $renter->update([
            'whatsapp_phone_e164' => $phone,
            'whatsapp_verified_at' => now(),
        ]);

        $session->update([
            'state' => WhatsappSession::STATE_LINKED,
            'renter_id' => $renter->id,
            'otp_code_hash' => null,
            'otp_expires_at' => null,
            'pending_email' => null,
            'otp_attempts' => 0,
            'magic_link_token_hash' => null,
            'magic_link_expires_at' => null,
            'chat_flow' => null,
            'chat_context' => null,
        ]);

        $this->sendLinkedWelcome($instance, $phone, $session->remote_jid, $renter, null, true);
    }

    private function stepWelcomeOrEmail(WhatsappSession $session, string $instance, string $phone, string $text): void
    {
        if (filter_var($text, FILTER_VALIDATE_EMAIL)) {
            $this->issueOtpForEmail($session, $instance, $phone, strtolower($text));

            return;
        }

        $session->state = WhatsappSession::STATE_AWAIT_EMAIL;
        $session->save();

        $this->checkoutServicesMenu->sendMainMenu($instance, $phone);
    }

    private function stepRequestEmail(WhatsappSession $session, string $instance, string $phone, string $text): void
    {
        if ($this->isCheckoutServicesCommand($text)) {
            $this->checkoutServicesMenu->handleCommand($session->fresh(), $instance, $phone, $text);

            return;
        }

        if (! filter_var($text, FILTER_VALIDATE_EMAIL)) {
            $this->client->sendText(
                $instance,
                $phone,
                'Send a valid *email* to link your rentals account, or *MENU* for services.'
            );

            return;
        }

        $this->issueOtpForEmail($session, $instance, $phone, strtolower($text));
    }

    private function issueOtpForEmail(WhatsappSession $session, string $instance, string $phone, string $email): void
    {
        $renter = Renter::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('is_active', true)
            ->first();

        if (! $renter) {
            $this->client->sendText(
                $instance,
                $phone,
                "We could not find an active account for that email.\n\n".
                'Please register on the website first, then try again with the same email.'
            );

            return;
        }

        $existing = Renter::query()
            ->where('whatsapp_phone_e164', $phone)
            ->where('id', '!=', $renter->id)
            ->exists();

        if ($existing) {
            $this->client->sendText(
                $instance,
                $phone,
                'This WhatsApp number is already linked to a different account. Contact support if you need help.'
            );

            return;
        }

        $code = (string) random_int(100000, 999999);
        $ttl = (int) config('whatsapp.otp.ttl_minutes', 10);
        $expires = now()->addMinutes($ttl);

        $plainMagic = Str::random(48);
        $magicHash = hash('sha256', $plainMagic);

        $session->fill([
            'state' => WhatsappSession::STATE_AWAIT_OTP,
            'pending_email' => $email,
            'otp_code_hash' => Hash::make($code),
            'otp_expires_at' => $expires,
            'otp_attempts' => 0,
            'magic_link_token_hash' => $magicHash,
            'magic_link_expires_at' => $expires,
        ]);
        $session->save();

        $base = rtrim((string) config('whatsapp.public_url', ''), '/');
        if ($base === '') {
            $base = rtrim((string) config('app.url'), '/');
        }
        $magicUrl = $base.'/whatsapp/link?'.http_build_query(['t' => $plainMagic]);

        try {
            Mail::to($renter->email)->send(new WhatsappLoginOtpMail($code, $ttl, $renter->name ?? 'there', $magicUrl));
        } catch (\Throwable $e) {
            Log::error('whatsapp.otp: mail failed', ['error' => $e->getMessage(), 'email' => $renter->email]);
            $session->update([
                'state' => WhatsappSession::STATE_AWAIT_EMAIL,
                'otp_code_hash' => null,
                'otp_expires_at' => null,
                'magic_link_token_hash' => null,
                'magic_link_expires_at' => null,
            ]);
            $this->client->sendText(
                $instance,
                $phone,
                'We could not send email right now. Please try again in a few minutes.'
            );

            return;
        }

        $this->client->sendText(
            $instance,
            $phone,
            "We emailed {$email}.\n\n*Option A:* open the email and tap *Confirm WhatsApp link*.\n*Option B:* reply here with the *6-digit code*.\n\nSame {$ttl}-minute expiry."
        );
    }

    private function stepVerifyOtp(WhatsappSession $session, string $instance, string $phone, string $text): void
    {
        if ($session->otp_expires_at === null || $session->otp_expires_at->isPast()) {
            $session->update([
                'state' => WhatsappSession::STATE_AWAIT_EMAIL,
                'otp_code_hash' => null,
                'otp_expires_at' => null,
                'pending_email' => null,
                'otp_attempts' => 0,
                'magic_link_token_hash' => null,
                'magic_link_expires_at' => null,
            ]);
            $this->client->sendText($instance, $phone, 'That code has expired. Send your email again to receive a new code.');

            return;
        }

        $max = (int) config('whatsapp.otp.max_attempts', 5);
        if ($session->otp_attempts >= $max) {
            $this->client->sendText($instance, $phone, 'Too many attempts. Please wait a while and start again by sending your email.');

            return;
        }

        $digits = preg_replace('/\D+/', '', $text) ?? '';
        if (strlen($digits) !== 6) {
            $session->increment('otp_attempts');
            $this->client->sendText($instance, $phone, 'Please send the 6-digit code from your email.');

            return;
        }

        if (! $session->otp_code_hash || ! Hash::check($digits, $session->otp_code_hash)) {
            $session->increment('otp_attempts');
            $this->client->sendText($instance, $phone, 'Invalid code. Check the email and try again.');

            return;
        }

        $email = $session->pending_email;
        if ($email === null) {
            $session->update(['state' => WhatsappSession::STATE_AWAIT_EMAIL]);

            return;
        }

        $renter = Renter::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('is_active', true)
            ->first();

        if (! $renter) {
            $session->update([
                'state' => WhatsappSession::STATE_WELCOME,
                'otp_code_hash' => null,
                'otp_expires_at' => null,
                'magic_link_token_hash' => null,
                'magic_link_expires_at' => null,
            ]);

            return;
        }

        $this->completeLinkAfterVerification($session, $renter);
    }

    private function sendLinkedWelcome(
        string $instance,
        string $phone,
        string $remoteJid,
        Renter $renter,
        ?string $text,
        bool $justLinked = false
    ): void {
        $session = WhatsappSession::query()->updateOrCreate(
            ['phone_e164' => $phone],
            [
                'remote_jid' => $remoteJid,
                'evolution_instance' => $instance,
                'state' => WhatsappSession::STATE_LINKED,
                'renter_id' => $renter->id,
            ]
        );

        $this->linkedMenu->handle(
            $renter->fresh(),
            $session->fresh(),
            $instance,
            $phone,
            $text ?? '',
            $justLinked
        );
    }
}
