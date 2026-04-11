<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappWallet;
use App\Models\WhatsappSession;
use App\Services\MevonRubiesVirtualAccountService;
use Illuminate\Support\Facades\Log;

/**
 * Tier 2: collect KYC (fname, lname, dob, gender, bvn, email), confirm WhatsApp number,
 * then Mevon Rubies POST /V1/createrubies: initiate → OTP (if required) → complete.
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
                'Tier 2 (permanent bank account) is not available yet on *'.$this->waBrand().'*. Try again later or use the web wallet link from *MENU*.'
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
            'You will get a *permanent* bank account for top-ups via *'.$this->waBrand()."*.\n".
            "Your *WhatsApp number* must match the number we send to the bank.\n\n".
            "Send your *first name* (as on your BVN).\n\n".
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
            'gender' => $this->stepGender($session, $instance, $phone, $text, $ctx),
            'bvn' => $this->stepBvn($session, $instance, $phone, $text, $ctx),
            'email' => $this->stepEmail($session, $instance, $phone, $text, $ctx),
            'confirm_phone' => $this->stepConfirmPhone($session, $instance, $phone, $text, $ctx, $cmd),
            'rubies_otp' => $this->stepRubiesOtp($session, $instance, $phone, $text, $ctx, $cmd),
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
        $ctx['step'] = 'gender';
        $session->update(['chat_context' => $ctx]);
        $this->client->sendText(
            $instance,
            $phone,
            'Send *M* for *male* or *F* for *female* (as on your BVN).'
        );
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function stepGender(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        array $ctx
    ): void {
        $t = strtoupper(trim($text));
        $gender = null;
        if (in_array($t, ['M', 'MALE'], true)) {
            $gender = 'male';
        }
        if (in_array($t, ['F', 'FEMALE'], true)) {
            $gender = 'female';
        }
        if ($gender === null) {
            $this->client->sendText($instance, $phone, 'Reply *M* for male or *F* for female.');

            return;
        }

        $ctx['gender'] = $gender;
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
            'We will register this *'.$this->waBrand()."* bank account for *this WhatsApp only*.\n\n".
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
            $init = $this->mevonRubies->initiateRubiesAccount(
                (string) ($ctx['fname'] ?? ''),
                (string) ($ctx['lname'] ?? ''),
                (string) ($ctx['gender'] ?? 'male'),
                $apiPhone,
                (string) ($ctx['bvn'] ?? ''),
            );

            if ($init['account_number'] !== '') {
                $this->finalizeTier2Success($session, $wallet, $instance, $phone, $ctx, $init);

                return;
            }

            if ($init['reference'] === '') {
                Log::warning('WhatsApp wallet Tier 2 Rubies initiate missing reference', [
                    'phone' => $phone,
                    'raw' => $init['raw'] ?? [],
                ]);
                $this->client->sendText(
                    $instance,
                    $phone,
                    'We could not start bank verification. Try *UPGRADE* again later or contact support.'
                );
                $session->update(['chat_flow' => null, 'chat_context' => null]);

                return;
            }

            $ctx['rubies_pending_reference'] = $init['reference'];
            $ctx['step'] = 'rubies_otp';
            $session->update(['chat_context' => $ctx]);

            $this->client->sendText(
                $instance,
                $phone,
                "*OTP sent*\n\n".
                "Check the phone number linked to your *BVN* for a code from the bank.\n\n".
                'Send the *OTP* here (usually *6 digits*).\n\n'.
                '*RESEND* — request another code if it expired.'
            );
        } catch (\Throwable $e) {
            Log::warning('WhatsApp wallet Tier 2 Mevon Rubies failed', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            $this->client->sendText(
                $instance,
                $phone,
                'We could not create your bank account right now. Try *UPGRADE* again later or use the web app from *MENU*.'
            );
            $session->update(['chat_flow' => null, 'chat_context' => null]);
        }
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array{account_number: string, account_name: string, bank_name: string, bank_code: string, reference: string, raw: array}  $va
     */
    private function stepRubiesOtp(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        array $ctx,
        string $cmd
    ): void {
        $ref = isset($ctx['rubies_pending_reference']) && is_string($ctx['rubies_pending_reference'])
            ? trim($ctx['rubies_pending_reference'])
            : '';
        if ($ref === '') {
            $this->recover($session, $instance, $phone);

            return;
        }

        if (str_starts_with($cmd, 'RESEND')) {
            try {
                $this->mevonRubies->resendRubiesOtp($ref);
                $this->client->sendText(
                    $instance,
                    $phone,
                    'If your number matches your BVN, a new OTP should arrive shortly. Send the code here when you receive it.'
                );
            } catch (\Throwable $e) {
                Log::warning('WhatsApp wallet Tier 2 Rubies resendOtp failed', [
                    'phone' => $phone,
                    'error' => $e->getMessage(),
                ]);
                $this->client->sendText(
                    $instance,
                    $phone,
                    'Could not resend the OTP right now. Try *RESEND* again in a minute or *CANCEL* and start *UPGRADE* over.'
                );
            }

            return;
        }

        $digits = preg_replace('/\D+/', '', $text) ?? '';
        if (strlen($digits) < 4 || strlen($digits) > 8) {
            $this->client->sendText(
                $instance,
                $phone,
                'Send the *OTP* from the bank (numbers only), or *RESEND* for a new code.'
            );

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
            $result = $this->mevonRubies->completeRubiesAccount($ref, $digits);
            $this->finalizeTier2Success($session, $wallet, $instance, $phone, $ctx, $result);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp wallet Tier 2 Rubies complete failed', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            $this->client->sendText(
                $instance,
                $phone,
                'That code did not work. Check the OTP and try again, or send *RESEND* for a new one.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array{account_number: string, account_name: string, bank_name: string, bank_code: string, reference: string, raw: array}  $va
     */
    private function finalizeTier2Success(
        WhatsappSession $session,
        WhatsappWallet $wallet,
        string $instance,
        string $phone,
        array $ctx,
        array $va
    ): void {
        $wallet->update([
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'kyc_fname' => (string) ($ctx['fname'] ?? ''),
            'kyc_lname' => (string) ($ctx['lname'] ?? ''),
            'kyc_gender' => (string) ($ctx['gender'] ?? ''),
            'kyc_dob' => (string) ($ctx['dob'] ?? ''),
            'kyc_bvn' => (string) ($ctx['bvn'] ?? ''),
            'kyc_email' => (string) ($ctx['email'] ?? ''),
            'kyc_verified_at' => now(),
            'mevon_virtual_account_number' => $va['account_number'],
            'mevon_bank_name' => $va['bank_name'],
            'mevon_bank_code' => $va['bank_code'],
            'mevon_reference' => $va['reference'] !== ''
                ? $va['reference']
                : (is_string($ctx['rubies_pending_reference'] ?? null) ? $ctx['rubies_pending_reference'] : $wallet->mevon_reference),
            'tier2_provisioned_at' => now(),
        ]);

        $session->update(['chat_flow' => null, 'chat_context' => null]);

        $this->client->sendText(
            $instance,
            $phone,
            "*Tier 2 active*\n\n".
            'Account: *'.$va['account_number']."*\n".
            'Bank: *'.($va['bank_name'] ?: 'RUBIES MFB')."*\n".
            'Name: *'.($va['account_name'] ?: '')."* \n\n".
            'Use this account to top up your WhatsApp wallet. *MENU* for more.'
        );
    }

    private function waBrand(): string
    {
        return (string) config('whatsapp.bot_brand_name', 'CheckoutNow');
    }

    private function recover(WhatsappSession $session, string $instance, string $phone): void
    {
        $session->update(['chat_flow' => null, 'chat_context' => null]);
        $this->client->sendText($instance, $phone, 'Session reset. Send *UPGRADE* to try Tier 2 again.');
    }
}
