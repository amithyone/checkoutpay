<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappWallet;
use App\Models\WhatsappSession;
use App\Services\MevonRubiesVirtualAccountService;
use Illuminate\Support\Facades\Log;

/**
 * Tier 2: collect KYC (fname, lname, dob, bvn, email), confirm WhatsApp number, call Mevon Rubies create.
 */
class WhatsappWalletUpgradeFlowHandler
{
    public const FLOW = 'wa_wallet_tier2';

    public function __construct(
        private EvolutionWhatsAppClient $client,
        private MevonRubiesVirtualAccountService $mevonRubies,
    ) {}

    public function start(WhatsappSession $session, string $instance, string $phone): void
    {
        $wallet = WhatsappWallet::query()->firstOrCreate(
            ['phone_e164' => $phone],
            [
                'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
                'balance' => 0,
                'status' => WhatsappWallet::STATUS_ACTIVE,
            ]
        );

        if ($wallet->tier >= WhatsappWallet::TIER_RUBIES_VA && $wallet->mevon_virtual_account_number) {
            $this->client->sendText(
                $instance,
                $phone,
                '*Tier 2* is already active on this number. Your dedicated account: *'.
                $wallet->mevon_virtual_account_number."*\n\n".
                'Bank: *'.($wallet->mevon_bank_name ?? 'Rubies MFB').'*'
            );

            return;
        }

        if (! $this->mevonRubies->isConfigured()) {
            $this->client->sendText(
                $instance,
                $phone,
                'Tier 2 (permanent bank account) is not available yet — Mevon Rubies is not configured. Try again later or use the web wallet link from *MENU*.'
            );

            return;
        }

        $session->update([
            'chat_flow' => self::FLOW,
            'chat_context' => ['step' => 'fname'],
        ]);

        $this->client->sendText(
            $instance,
            $phone,
            "*Tier 2 — full KYC*\n\n".
            "You will get a *permanent* Rubies account for top-ups (Mevon Pay).\n".
            "Your *WhatsApp number* must match the number we send to the bank.\n\n".
            "Send your *first name* (as on BVN).\n\n".
            '*CANCEL* — exit'
        );
    }

    public function handle(WhatsappSession $session, string $instance, string $phone, string $text, string $cmd): void
    {
        $ctx = $session->chat_context;
        if (! is_array($ctx)) {
            $ctx = [];
        }

        if (in_array($cmd, ['CANCEL', 'EXIT', 'BACK'], true)) {
            $session->update(['chat_flow' => null, 'chat_context' => null]);
            $this->client->sendText($instance, $phone, 'Tier 2 signup cancelled. *MENU* for services.');

            return;
        }

        $step = (string) ($ctx['step'] ?? 'fname');

        match ($step) {
            'fname' => $this->stepFname($session, $instance, $phone, $text, $ctx),
            'lname' => $this->stepLname($session, $instance, $phone, $text, $ctx),
            'dob' => $this->stepDob($session, $instance, $phone, $text, $ctx),
            'bvn' => $this->stepBvn($session, $instance, $phone, $text, $ctx),
            'email' => $this->stepEmail($session, $instance, $phone, $text, $ctx),
            'confirm_phone' => $this->stepConfirmPhone($session, $instance, $phone, $text, $ctx, $cmd),
            default => $this->recover($session, $instance, $phone),
        };
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function stepFname(WhatsappSession $session, string $instance, string $phone, string $text, array $ctx): void
    {
        $name = trim($text);
        if (strlen($name) < 2) {
            $this->client->sendText($instance, $phone, 'Send your first name (at least 2 characters).');

            return;
        }

        $ctx['fname'] = $name;
        $ctx['step'] = 'lname';
        $session->update(['chat_context' => $ctx]);
        $this->client->sendText($instance, $phone, 'Send your *last name* (as on BVN).');
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function stepLname(WhatsappSession $session, string $instance, string $phone, string $text, array $ctx): void
    {
        $name = trim($text);
        if (strlen($name) < 2) {
            $this->client->sendText($instance, $phone, 'Send your last name (at least 2 characters).');

            return;
        }

        $ctx['lname'] = $name;
        $ctx['step'] = 'dob';
        $session->update(['chat_context' => $ctx]);
        $this->client->sendText($instance, $phone, 'Send date of birth: *YYYY-MM-DD* (e.g. 1990-05-15).');
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function stepDob(WhatsappSession $session, string $instance, string $phone, string $text, array $ctx): void
    {
        $raw = trim($text);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            $this->client->sendText($instance, $phone, 'Use format *YYYY-MM-DD*.');

            return;
        }

        try {
            $d = new \DateTimeImmutable($raw);
        } catch (\Throwable $e) {
            $this->client->sendText($instance, $phone, 'That date is not valid. Try again.');

            return;
        }

        $ctx['dob'] = $d->format('Y-m-d');
        $ctx['step'] = 'bvn';
        $session->update(['chat_context' => $ctx]);
        $this->client->sendText($instance, $phone, 'Send your *11-digit BVN* (numbers only).');
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function stepBvn(WhatsappSession $session, string $instance, string $phone, string $text, array $ctx): void
    {
        $digits = preg_replace('/\D+/', '', $text) ?? '';
        if (strlen($digits) !== 11) {
            $this->client->sendText($instance, $phone, 'BVN must be exactly 11 digits.');

            return;
        }

        $ctx['bvn'] = $digits;
        $ctx['step'] = 'email';
        $session->update(['chat_context' => $ctx]);
        $this->client->sendText($instance, $phone, 'Send your *email address*.');
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function stepEmail(WhatsappSession $session, string $instance, string $phone, string $text, array $ctx): void
    {
        $email = strtolower(trim($text));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->client->sendText($instance, $phone, 'Send a valid email address.');

            return;
        }

        $ctx['email'] = $email;
        $ctx['step'] = 'confirm_phone';
        $session->update(['chat_context' => $ctx]);

        $local = PhoneNormalizer::e164DigitsToNgLocal11($phone) ?? $phone;
        $this->client->sendText(
            $instance,
            $phone,
            "We will register this Rubies account for *this WhatsApp only*.\n\n".
            "Detected number: *{$local}*\n\n".
            "Reply *YES* if this is correct and matches the SIM on this chat.\n".
            'If not, send *CANCEL* — you must use WhatsApp on the same phone you register.'
        );
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function stepConfirmPhone(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        array $ctx,
        string $cmd
    ): void {
        if (! in_array($cmd, ['YES', 'Y', 'OK'], true)) {
            $this->client->sendText($instance, $phone, 'Reply *YES* to confirm this WhatsApp number, or *CANCEL*.');

            return;
        }

        $apiPhone = PhoneNormalizer::e164DigitsToNgLocal11($phone);
        if ($apiPhone === null) {
            $this->client->sendText($instance, $phone, 'Could not read your WhatsApp number. Contact support.');

            return;
        }

        $wallet = WhatsappWallet::query()->firstOrCreate(
            ['phone_e164' => $phone],
            [
                'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
                'balance' => 0,
                'status' => WhatsappWallet::STATUS_ACTIVE,
            ]
        );

        try {
            $result = $this->mevonRubies->createRubiesAccount([
                'action' => 'create',
                'fname' => (string) ($ctx['fname'] ?? ''),
                'lname' => (string) ($ctx['lname'] ?? ''),
                'phone' => $apiPhone,
                'dob' => (string) ($ctx['dob'] ?? ''),
                'bvn' => (string) ($ctx['bvn'] ?? ''),
                'email' => (string) ($ctx['email'] ?? ''),
            ]);

            $wallet->update([
                'tier' => WhatsappWallet::TIER_RUBIES_VA,
                'kyc_fname' => (string) ($ctx['fname'] ?? ''),
                'kyc_lname' => (string) ($ctx['lname'] ?? ''),
                'kyc_dob' => (string) ($ctx['dob'] ?? ''),
                'kyc_bvn' => (string) ($ctx['bvn'] ?? ''),
                'kyc_email' => (string) ($ctx['email'] ?? ''),
                'kyc_verified_at' => now(),
                'mevon_virtual_account_number' => $result['account_number'],
                'mevon_bank_name' => $result['bank_name'],
                'mevon_bank_code' => $result['bank_code'],
                'mevon_reference' => $result['reference'] ?? null,
                'tier2_provisioned_at' => now(),
            ]);

            $session->update(['chat_flow' => null, 'chat_context' => null]);

            $this->client->sendText(
                $instance,
                $phone,
                "*Tier 2 active*\n\n".
                'Account: *'.$result['account_number']."*\n".
                'Bank: *'.($result['bank_name'] ?: 'RUBIES MFB')."*\n".
                'Name: *'.($result['account_name'] ?: '')."* \n\n".
                'Use this account to top up your WhatsApp wallet. *MENU* for more.'
            );
        } catch (\Throwable $e) {
            Log::warning('WhatsApp wallet Tier 2 Mevon Rubies failed', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            $this->client->sendText(
                $instance,
                $phone,
                'We could not create your bank account right now: '.$e->getMessage()."\n\nTry *UPGRADE* again later or use the web app."
            );
            $session->update(['chat_flow' => null, 'chat_context' => null]);
        }
    }

    private function recover(WhatsappSession $session, string $instance, string $phone): void
    {
        $session->update(['chat_flow' => null, 'chat_context' => null]);
        $this->client->sendText($instance, $phone, 'Session reset. Send *UPGRADE* to try Tier 2 again.');
    }
}
