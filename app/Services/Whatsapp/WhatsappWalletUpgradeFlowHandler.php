<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappSession;
use App\Models\WhatsappWallet;
use App\Services\MevonPay\PrivateAccountProvisionService;
use App\Support\WhatsappWalletKycInputGuard;
use Illuminate\Support\Facades\Log;

/**
 * Tier 2: collect KYC, confirm WhatsApp number, then queue Mevon /V1/pivateaccount.
 * Personal: fname, lname, dob, gender, bvn, email → create_business_account with person name in business_name.
 * Business: CAC, signatory BVN, dob, email → create_business_account with company name.
 */
class WhatsappWalletUpgradeFlowHandler
{
    public const FLOW = 'wa_wallet_tier2';

    public function __construct(
        private EvolutionWhatsAppClient $client,
        private PrivateAccountProvisionService $provision,
        private WhatsappWalletCountryResolver $walletCountry,
    ) {}

    public function start(WhatsappSession $session, string $instance, string $phone, bool $resumePinReset = false): void
    {
        $wallet = WhatsappWallet::query()->firstOrCreate(
            ['phone_e164' => $phone],
            [
                'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
                'balance' => 0,
                'status' => WhatsappWallet::STATUS_ACTIVE,
            ]
        );

        if (! $this->walletCountry->isNigeriaPayInWallet((string) $wallet->phone_e164)) {
            $this->client->sendText(
                $instance,
                $phone,
                '*UPGRADE* (Tier 2 bank pay-in) is only available for *Nigeria* numbers right now. Use *4* for WhatsApp wallet sends.'
            );

            return;
        }

        if ($wallet->tier >= WhatsappWallet::TIER_RUBIES_VA && $wallet->mevon_virtual_account_number) {
            $this->kycLog('info', 'whatsapp.wallet.kyc.upgrade_requested', [
                'outcome' => 'already_tier2',
                'whatsapp_wallet_id' => $wallet->id,
                'phone' => $phone,
                'instance' => $instance,
            ]);
            $this->client->sendText(
                $instance,
                $phone,
                '*Tier 2* is already active on this number. Your dedicated account: *'.
                $wallet->mevon_virtual_account_number."*\n\n".
                'Bank: *'.($wallet->mevon_bank_name ?? 'Rubies MFB').'*'
            );

            return;
        }

        if (! $this->provision->isConfigured()) {
            $this->kycLog('info', 'whatsapp.wallet.kyc.upgrade_requested', [
                'outcome' => 'tier2_unavailable',
                'whatsapp_wallet_id' => $wallet->id,
                'phone' => $phone,
                'instance' => $instance,
            ]);
            $this->client->sendText(
                $instance,
                $phone,
                'Tier 2 (permanent bank account) is not available yet on *'.$this->waBrand().'*. Try again later or use the web wallet link from *MENU*.'
            );

            return;
        }

        $this->kycLog('info', 'whatsapp.wallet.kyc.upgrade_requested', [
            'outcome' => 'kyc_flow_started',
            'whatsapp_wallet_id' => $wallet->id,
            'phone' => $phone,
            'instance' => $instance,
            'tier_before' => (int) $wallet->tier,
        ]);

        $ctx = ['step' => 'account_kind'];
        if ($resumePinReset) {
            $ctx['resume_pin_reset'] = true;
        }

        $session->update([
            'chat_flow' => self::FLOW,
            'chat_context' => $ctx,
        ]);

        $this->client->sendText(
            $instance,
            $phone,
            "Nice — let's set up your Tier 2 account 🏦\n\n".
            "You'll get a *permanent* bank number for topping up via *".$this->waBrand()."*.\n".
            "No separate bank *OTP* step — we create the account once your details are confirmed.\n".
            "The number on this WhatsApp chat should match what the bank has on file.\n\n".
            "Who is this account for?\n".
            "· Reply *1* or *PERSONAL* — your name + BVN (individual)\n".
            "· Reply *2* or *BUSINESS* — company CAC number (business)\n\n".
            '*CANCEL* to stop · *0* back · *00* menu · *000* main'
        );
    }

    public function handle(WhatsappSession $session, string $instance, string $phone, string $text, string $cmd): void
    {
        $ctx = $session->chat_context;
        if (! is_array($ctx)) {
            $ctx = [];
        }

        $walletId = WhatsappWallet::query()->where('phone_e164', $phone)->value('id');
        $step = (string) ($ctx['step'] ?? 'fname');

        $this->kycLog('info', 'whatsapp.wallet.kyc.inbound', array_merge([
            'whatsapp_wallet_id' => $walletId,
            'phone' => $phone,
            'instance' => $instance,
            'step' => $step,
            'cmd' => $cmd,
        ], $this->describeUserInputForLog($step, $text, $cmd)));

        if (in_array($cmd, ['CANCEL', 'EXIT', 'BACK'], true)) {
            $this->kycLog('info', 'whatsapp.wallet.kyc.flow_cancelled', [
                'whatsapp_wallet_id' => $walletId,
                'phone' => $phone,
                'instance' => $instance,
                'step' => $step,
            ]);
            $session->update(['chat_flow' => null, 'chat_context' => null]);
            $this->client->sendText($instance, $phone, 'Tier 2 signup cancelled. *MENU* for services.');

            return;
        }

        match ($step) {
            'account_kind' => $this->stepAccountKind($session, $instance, $phone, $text, $ctx),
            'cac' => $this->stepCac($session, $instance, $phone, $text, $ctx),
            'business_name' => $this->stepBusinessName($session, $instance, $phone, $text, $ctx),
            'fname' => $this->stepFname($session, $instance, $phone, $text, $ctx),
            'lname' => $this->stepLname($session, $instance, $phone, $text, $ctx),
            'dob' => $this->stepDob($session, $instance, $phone, $text, $ctx),
            'gender' => $this->stepGender($session, $instance, $phone, $text, $ctx),
            'bvn' => $this->stepBvn($session, $instance, $phone, $text, $ctx),
            'email' => $this->stepEmail($session, $instance, $phone, $text, $ctx),
            'confirm_phone' => $this->stepConfirmPhone($session, $instance, $phone, $text, $ctx, $cmd),
            'rubies_otp' => $this->recover($session, $instance, $phone),
            default => $this->recover($session, $instance, $phone),
        };
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function stepAccountKind(WhatsappSession $session, string $instance, string $phone, string $text, array $ctx): void
    {
        $t = strtoupper(trim($text));
        if (in_array($t, ['1', 'P', 'PERSONAL', 'INDIVIDUAL'], true)) {
            $ctx['rubies_account_type'] = 'personal';
            $ctx['step'] = 'fname';
            $session->update(['chat_context' => $ctx]);
            $this->client->sendText(
                $instance,
                $phone,
                "Personal account — what's your *first name* exactly as on your BVN?\n\n".
                '*CANCEL* to stop'
            );

            return;
        }
        if (in_array($t, ['2', 'B', 'BUSINESS', 'COMPANY', 'CAC'], true)) {
            $ctx['rubies_account_type'] = 'business';
            $ctx['step'] = 'cac';
            $session->update(['chat_context' => $ctx]);
            $this->client->sendText(
                $instance,
                $phone,
                "Business account — send your *CAC* / company registration number (e.g. *RC123456*).\n\n".
                '*CANCEL* to stop'
            );

            return;
        }

        $this->kycLog('notice', 'whatsapp.wallet.kyc.validation_failed', [
            'phone' => $phone,
            'instance' => $instance,
            'step' => 'account_kind',
            'reason' => 'not_personal_or_business',
            'value' => substr($t, 0, 16),
        ]);
        $this->client->sendText($instance, $phone, 'Reply *1* for personal or *2* for business.');
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function stepCac(WhatsappSession $session, string $instance, string $phone, string $text, array $ctx): void
    {
        $cac = strtoupper(trim($text));
        $cac = preg_replace('/\s+/', '', $cac) ?? $cac;
        if (strlen($cac) < 3 || strlen($cac) > 100) {
            $this->kycLog('notice', 'whatsapp.wallet.kyc.validation_failed', [
                'phone' => $phone,
                'instance' => $instance,
                'step' => 'cac',
                'reason' => 'bad_length',
                'length' => strlen($cac),
            ]);
            $this->client->sendText($instance, $phone, 'Send a valid CAC / registration number (e.g. RC123456).');

            return;
        }
        if ($cacErr = WhatsappWalletKycInputGuard::cacError($cac)) {
            $this->kycLog('notice', 'whatsapp.wallet.kyc.validation_failed', [
                'phone' => $phone,
                'instance' => $instance,
                'step' => 'cac',
                'reason' => 'generic_or_invalid',
            ]);
            $this->client->sendText($instance, $phone, $cacErr);

            return;
        }

        $ctx['cac'] = $cac;
        $ctx['step'] = 'business_name';
        $session->update(['chat_context' => $ctx]);
        $this->client->sendText(
            $instance,
            $phone,
            'Send the *registered company name* exactly as on CAC (not the RC/BN number).'
        );
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function stepBusinessName(WhatsappSession $session, string $instance, string $phone, string $text, array $ctx): void
    {
        $name = trim($text);
        $cac = strtoupper(trim((string) ($ctx['cac'] ?? '')));
        if (strlen($name) < 3) {
            $this->kycLog('notice', 'whatsapp.wallet.kyc.validation_failed', [
                'phone' => $phone,
                'instance' => $instance,
                'step' => 'business_name',
                'reason' => 'too_short',
                'length' => strlen($name),
            ]);
            $this->client->sendText($instance, $phone, 'Send the registered company name (at least 3 characters).');

            return;
        }
        if ($cac !== '' && strcasecmp(preg_replace('/\s+/', '', $name) ?? '', preg_replace('/\s+/', '', $cac) ?? '') === 0) {
            $this->client->sendText(
                $instance,
                $phone,
                'That looks like the CAC number. Send the *company name* on the registration certificate.'
            );

            return;
        }

        $ctx['business_name'] = $name;
        $ctx['step'] = 'fname';
        $session->update(['chat_context' => $ctx]);
        $this->client->sendText($instance, $phone, 'Send the *signatory first name* (as on BVN).');
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function stepFname(WhatsappSession $session, string $instance, string $phone, string $text, array $ctx): void
    {
        $name = trim($text);
        if (strlen($name) < 2) {
            $this->kycLog('notice', 'whatsapp.wallet.kyc.validation_failed', [
                'phone' => $phone,
                'instance' => $instance,
                'step' => 'fname',
                'reason' => 'too_short',
                'length' => strlen($name),
            ]);
            $this->client->sendText($instance, $phone, 'Send your first name (at least 2 characters).');

            return;
        }

        $ctx['fname'] = $name;
        $ctx['step'] = 'lname';
        $session->update(['chat_context' => $ctx]);
        $isBusiness = ($ctx['rubies_account_type'] ?? 'personal') === 'business';
        $this->client->sendText(
            $instance,
            $phone,
            $isBusiness ? 'Send the *signatory last name* (as on BVN).' : 'Send your *last name* (as on BVN).'
        );
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function stepLname(WhatsappSession $session, string $instance, string $phone, string $text, array $ctx): void
    {
        $name = trim($text);
        if (strlen($name) < 2) {
            $this->kycLog('notice', 'whatsapp.wallet.kyc.validation_failed', [
                'phone' => $phone,
                'instance' => $instance,
                'step' => 'lname',
                'reason' => 'too_short',
                'length' => strlen($name),
            ]);
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
            $this->kycLog('notice', 'whatsapp.wallet.kyc.validation_failed', [
                'phone' => $phone,
                'instance' => $instance,
                'step' => 'dob',
                'reason' => 'bad_format',
                'value' => $raw,
            ]);
            $this->client->sendText($instance, $phone, 'Use format *YYYY-MM-DD*.');

            return;
        }

        try {
            $d = new \DateTimeImmutable($raw);
        } catch (\Throwable $e) {
            $this->kycLog('notice', 'whatsapp.wallet.kyc.validation_failed', [
                'phone' => $phone,
                'instance' => $instance,
                'step' => 'dob',
                'reason' => 'invalid_date',
                'value' => $raw,
                'error' => $e->getMessage(),
            ]);
            $this->client->sendText($instance, $phone, 'That date is not valid. Try again.');

            return;
        }

        $ctx['dob'] = $d->format('Y-m-d');
        if (($ctx['rubies_account_type'] ?? 'personal') === 'business') {
            $ctx['step'] = 'bvn';
            $session->update(['chat_context' => $ctx]);
            $this->client->sendText($instance, $phone, 'Send the signatory *11-digit BVN* (numbers only).');

            return;
        }

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
            $this->kycLog('notice', 'whatsapp.wallet.kyc.validation_failed', [
                'phone' => $phone,
                'instance' => $instance,
                'step' => 'gender',
                'reason' => 'not_m_or_f',
                'value' => substr($t, 0, 16),
            ]);
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
            $this->kycLog('notice', 'whatsapp.wallet.kyc.validation_failed', [
                'phone' => $phone,
                'instance' => $instance,
                'step' => 'bvn',
                'reason' => 'bad_digit_count',
                'digit_count' => strlen($digits),
            ]);
            $this->client->sendText($instance, $phone, 'BVN must be exactly 11 digits.');

            return;
        }
        if ($bvnErr = WhatsappWalletKycInputGuard::bvnOrNinError($digits, 'BVN')) {
            $this->kycLog('notice', 'whatsapp.wallet.kyc.validation_failed', [
                'phone' => $phone,
                'instance' => $instance,
                'step' => 'bvn',
                'reason' => 'generic_or_fake',
            ]);
            $this->client->sendText($instance, $phone, $bvnErr);

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
            $this->kycLog('notice', 'whatsapp.wallet.kyc.validation_failed', [
                'phone' => $phone,
                'instance' => $instance,
                'step' => 'email',
                'reason' => 'invalid_email',
                'value' => $email,
            ]);
            $this->client->sendText($instance, $phone, 'Send a valid email address.');

            return;
        }
        if ($emailErr = WhatsappWalletKycInputGuard::emailError($email)) {
            $this->kycLog('notice', 'whatsapp.wallet.kyc.validation_failed', [
                'phone' => $phone,
                'instance' => $instance,
                'step' => 'email',
                'reason' => 'domain_not_allowed',
                'value' => $email,
            ]);
            $this->client->sendText($instance, $phone, $emailErr);

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
            $this->kycLog('notice', 'whatsapp.wallet.kyc.validation_failed', [
                'phone' => $phone,
                'instance' => $instance,
                'step' => 'confirm_phone',
                'reason' => 'not_yes',
                'cmd' => $cmd,
            ]);
            $this->client->sendText($instance, $phone, 'Reply *YES* to confirm this WhatsApp number, or *CANCEL*.');

            return;
        }

        $apiPhone = PhoneNormalizer::e164DigitsToNgLocal11($phone);
        if ($apiPhone === null) {
            $this->kycLog('error', 'whatsapp.wallet.kyc.phone_normalization_failed', [
                'phone' => $phone,
                'instance' => $instance,
                'step' => 'confirm_phone',
            ]);
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
            $accountType = (string) ($ctx['rubies_account_type'] ?? 'personal');
            if ($accountType === 'business') {
                $result = $this->provision->dispatchPersonalBusinessIfReady($wallet, [
                    'cac' => (string) ($ctx['cac'] ?? ''),
                    'dob' => (string) ($ctx['dob'] ?? ''),
                    'email' => (string) ($ctx['email'] ?? ''),
                    'bvn' => (string) ($ctx['bvn'] ?? ''),
                    'fname' => (string) ($ctx['fname'] ?? ''),
                    'lname' => (string) ($ctx['lname'] ?? ''),
                    'business_name' => (string) ($ctx['business_name'] ?? ''),
                ]);
            } else {
                $result = $this->provision->dispatchPersonalIfReady($wallet, [
                    'fname' => (string) ($ctx['fname'] ?? ''),
                    'lname' => (string) ($ctx['lname'] ?? ''),
                    'dob' => (string) ($ctx['dob'] ?? ''),
                    'email' => (string) ($ctx['email'] ?? ''),
                    'gender' => (string) ($ctx['gender'] ?? ''),
                    'bvn' => (string) ($ctx['bvn'] ?? ''),
                ]);
            }

            if (! $result['dispatched']) {
                throw new \RuntimeException($result['message']);
            }

            $this->kycLog('info', 'whatsapp.wallet.kyc.private_account_queued', [
                'phone' => $phone,
                'instance' => $instance,
                'whatsapp_wallet_id' => $wallet->id,
                'rubies_account_type' => $accountType,
            ]);

            $priorCtx = is_array($session->chat_context) ? $session->chat_context : [];
            $resumePinReset = (bool) ($priorCtx['resume_pin_reset'] ?? false);
            $session->update(['chat_flow' => null, 'chat_context' => null]);

            $this->client->sendText(
                $instance,
                $phone,
                "*Account setup started*\n\n".
                "Your permanent bank account is being created. Check *MENU* in a few minutes for your account details.\n\n".
                ($resumePinReset
                    ? 'After your account appears, send *PIN RESET* again to continue.'
                    : 'Use *MENU* anytime to view your wallet.')
            );
        } catch (\Throwable $e) {
            $this->kycLog('error', 'whatsapp.wallet.kyc.rubies_create_exception', [
                'phone' => $phone,
                'instance' => $instance,
                'whatsapp_wallet_id' => $wallet->id,
                'exception_class' => $e::class,
                'error' => $e->getMessage(),
            ]);
            $this->client->sendText(
                $instance,
                $phone,
                'We could not create your bank account right now. Check your details and try *UPGRADE* again, or use the web app from *MENU*.'
            );
            $session->update(['chat_flow' => null, 'chat_context' => null]);
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
        $accountType = (string) ($ctx['rubies_account_type'] ?? 'personal');
        $isBusiness = $accountType === 'business';

        $update = [
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'rubies_account_type' => $isBusiness ? 'business' : 'personal',
            'kyc_cac' => $isBusiness ? (string) ($ctx['cac'] ?? '') : null,
            'kyc_business_name' => $isBusiness ? (string) ($ctx['business_name'] ?? '') : null,
            'kyc_fname' => $isBusiness ? null : (string) ($ctx['fname'] ?? ''),
            'kyc_lname' => $isBusiness ? null : (string) ($ctx['lname'] ?? ''),
            'kyc_gender' => $isBusiness ? null : (string) ($ctx['gender'] ?? ''),
            'kyc_dob' => (string) ($ctx['dob'] ?? ''),
            'kyc_bvn' => $isBusiness ? null : (string) ($ctx['bvn'] ?? ''),
            'kyc_email' => (string) ($ctx['email'] ?? ''),
            'kyc_verified_at' => now(),
            'mevon_virtual_account_number' => $va['account_number'],
            'mevon_account_name' => trim((string) ($va['account_name'] ?? '')) ?: null,
            'mevon_bank_name' => $va['bank_name'],
            'mevon_bank_code' => $va['bank_code'],
            'mevon_reference' => $va['reference'] !== '' ? $va['reference'] : $wallet->mevon_reference,
            'tier2_provisioned_at' => now(),
        ];
        $verifiedSenderName = $wallet->resolveSenderNameAfterTier2(
            (string) ($va['account_name'] ?? ''),
            $isBusiness ? null : (string) ($ctx['fname'] ?? ''),
            $isBusiness ? null : (string) ($ctx['lname'] ?? ''),
        );
        if ($verifiedSenderName !== null) {
            $update['sender_name'] = $verifiedSenderName;
        }

        $wallet->update($update);

        $priorCtx = is_array($session->chat_context) ? $session->chat_context : [];
        $resumePinReset = (bool) ($priorCtx['resume_pin_reset'] ?? false);

        $this->kycLog('info', 'whatsapp.wallet.kyc.tier2_completed', [
            'phone' => $phone,
            'instance' => $instance,
            'whatsapp_wallet_id' => $wallet->id,
            'account_suffix' => substr((string) $va['account_number'], -4),
            'bank_name' => $va['bank_name'] ?? null,
            'mevon_reference_suffix' => ($va['reference'] ?? '') !== ''
                ? substr((string) $va['reference'], -8)
                : null,
            'resume_pin_reset' => $resumePinReset,
        ]);

        if ($resumePinReset) {
            app(WhatsappWalletPinResetFlowHandler::class)->beginNameVerificationAfterUpgrade(
                $session->fresh(),
                $instance,
                $phone,
                $wallet->fresh()
            );

            return;
        }

        $session->update(['chat_flow' => null, 'chat_context' => null]);

        $label = $isBusiness ? 'Business Tier 2 active' : 'Tier 2 active';
        $this->client->sendText(
            $instance,
            $phone,
            "*{$label}*\n\n".
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
        $this->kycLog('warning', 'whatsapp.wallet.kyc.session_recovered', [
            'phone' => $phone,
            'instance' => $instance,
        ]);
        $session->update(['chat_flow' => null, 'chat_context' => null]);
        $this->client->sendText($instance, $phone, 'Session reset. Send *UPGRADE* to try Tier 2 again.');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function kycLog(string $level, string $message, array $context = []): void
    {
        Log::channel('whatsapp_wallet_kyc')->log($level, $message, $context);
    }

    /**
     * @return array<string, mixed>
     */
    private function describeUserInputForLog(string $step, string $text, string $cmd): array
    {
        $trim = trim($text);
        $cmdU = strtoupper($cmd);

        return match ($step) {
            'account_kind' => ['input_type' => 'account_kind', 'value' => strtoupper(substr($trim, 0, 32))],
            'cac' => [
                'input_type' => 'cac',
                'length' => strlen(preg_replace('/\s+/', '', strtoupper($trim)) ?? ''),
            ],
            'business_name' => ['input_type' => 'business_name', 'value' => substr($trim, 0, 80)],
            'bvn' => [
                'input_type' => 'bvn',
                'digit_count' => strlen(preg_replace('/\D+/', '', $trim) ?? ''),
            ],
            'email' => ['input_type' => 'email', 'value' => $trim],
            'dob' => ['input_type' => 'dob', 'value' => $trim],
            'gender' => ['input_type' => 'gender', 'value' => strtoupper(substr($trim, 0, 16))],
            'fname', 'lname' => ['input_type' => $step, 'value' => $trim],
            'confirm_phone' => [
                'input_type' => 'confirm_phone',
                'cmd' => $cmdU,
                'unexpected_text' => $trim !== '' && ! in_array($cmdU, ['YES', 'Y', 'OK'], true)
                    ? substr($trim, 0, 80)
                    : null,
            ],
            default => ['input_type' => $step, 'text_preview' => substr($trim, 0, 160)],
        };
    }

    /**
     * @param  array<mixed>  $raw
     * @return array<string, mixed>
     */
    private function summarizeRubiesRaw(array $raw): array
    {
        if ($raw === []) {
            return [];
        }

        $json = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            return ['encode_error' => true];
        }

        return [
            'length' => strlen($json),
            'preview' => substr($json, 0, 2000),
        ];
    }
}
