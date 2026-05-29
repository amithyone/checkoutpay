<?php

namespace App\Services\Support;

use App\Models\ConsumerWalletApiAccount;
use App\Models\SupportIntakeSession;
use App\Models\SupportTicket;
use App\Models\WhatsappWallet;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SupportIntakeService
{
    public const STEP_DISCLAIMER = 'disclaimer';

    public const STEP_PAYMENT_ISSUE = 'payment_issue';

    public const STEP_DESTINATION_ACCOUNT = 'destination_account';

    public const STEP_SESSION_ID = 'session_id';

    public const STEP_NAME = 'name';

    public const STEP_AMOUNT = 'amount';

    public const STEP_BANK_FROM = 'bank_from';

    public const STEP_RECEIPT = 'receipt';

    public const STEP_CONTACT_MODE = 'contact_mode';

    public const STEP_PHONE = 'phone';

    public const STEP_DONE = 'done';

    public const STEP_RESTART = 'restart';

    public function __construct(
        private SupportPayeeAccountService $payeeAccounts,
        private SupportConversationService $conversations,
        private SupportWalletOnboardingService $onboarding,
        private SupportCountryOptionsService $countries,
        private SupportIssueOptionsService $issues,
        private SupportPaymentLookupService $payments,
        private SupportIntakeLockoutService $lockout,
    ) {}

    public function start(string $channel, ?int $consumerWalletApiAccountId = null, ?Request $request = null): array
    {
        if (! in_array($channel, SupportTicket::publicChannels(), true)) {
            return ['ok' => false, 'message' => 'Invalid support channel.'];
        }

        if ($request) {
            $lock = $this->lockout->status($request);
            if ($lock['locked']) {
                return [
                    'ok' => false,
                    'message' => $this->lockoutMessage($lock['locked_until'] ?? null),
                    'locked_until' => $lock['locked_until'] ?? null,
                ];
            }
        }

        $token = (string) Str::uuid();
        $disclaimer = (string) config('support.intake_messages.disclaimer', '');

        $session = SupportIntakeSession::create([
            'intake_token' => $token,
            'channel' => $channel,
            'intake_status' => SupportIntakeSession::STATUS_IN_PROGRESS,
            'current_step' => self::STEP_PAYMENT_ISSUE,
            'consumer_wallet_api_account_id' => $consumerWalletApiAccountId,
            'last_visitor_ip' => $request?->ip(),
            'bot_messages' => [
                $this->botLine($disclaimer),
                $this->botLine((string) config('support.intake_messages.ask_payment_issue', '')),
            ],
        ]);

        return [
            'ok' => true,
            'session' => $session,
            'payload' => $this->sessionPayload($session, $request),
        ];
    }

    public function findByToken(string $token): ?SupportIntakeSession
    {
        return SupportIntakeSession::query()
            ->where('intake_token', $token)
            ->first();
    }

    /**
     * @return array{ok: bool, message?: string, session?: SupportIntakeSession, payload?: array<string, mixed>}
     */
    public function advance(SupportIntakeSession $session, string $step, mixed $value, Request $request): array
    {
        $lock = $this->lockout->status($request);
        if ($lock['locked']) {
            return [
                'ok' => false,
                'message' => $this->lockoutMessage($lock['locked_until'] ?? null),
                'locked_until' => $lock['locked_until'] ?? null,
            ];
        }

        if ($session->isLockedOut()) {
            return [
                'ok' => false,
                'message' => $this->lockoutMessage($session->locked_until?->toIso8601String()),
                'locked_until' => $session->locked_until?->toIso8601String(),
            ];
        }

        if ($session->isTerminal()) {
            return ['ok' => false, 'message' => 'This intake session is already finished.'];
        }

        $step = trim($step);
        $messages = is_array($session->bot_messages) ? $session->bot_messages : [];

        if ($step === self::STEP_RESTART) {
            return $this->restartIntake($session, $request, $messages);
        }

        if ($step === self::STEP_PAYMENT_ISSUE) {
            $isPayment = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            $session->is_payment_issue = $isPayment;
            $messages[] = $this->userLine($isPayment ? 'Yes, payment issue' : 'No, something else');

            if (! $isPayment) {
                $reject = (string) config('support.intake_messages.rejected_non_payment', '');
                $messages[] = $this->botLine($reject);
                $session->fill([
                    'intake_status' => SupportIntakeSession::STATUS_REJECTED_NON_PAYMENT,
                    'current_step' => self::STEP_DONE,
                    'bot_messages' => $messages,
                ])->save();

                return ['ok' => true, 'session' => $session->fresh(), 'payload' => $this->sessionPayload($session, $request)];
            }

            $session->issue_type = 'payment_pending_transfer';
            $messages[] = $this->botLine((string) config('support.intake_messages.ask_destination_account', ''));
            $session->fill([
                'current_step' => self::STEP_DESTINATION_ACCOUNT,
                'bot_messages' => $messages,
            ])->save();

            return ['ok' => true, 'session' => $session->fresh(), 'payload' => $this->sessionPayload($session, $request)];
        }

        if ($step === self::STEP_DESTINATION_ACCOUNT) {
            $account = SupportPayeeAccountService::normalizeAccountNumber((string) $value);
            if (strlen($account) < 10) {
                return ['ok' => false, 'message' => 'Please enter a valid account number (at least 10 digits).'];
            }

            $inPlatform = $this->payeeAccounts->isAccountInPlatform($account);
            if (! $inPlatform) {
                return $this->handleWrongAccount($session, $request, $messages, $account);
            }

            $messages[] = $this->userLine($account);
            $messages[] = $this->botLine((string) config('support.intake_messages.ask_session_id', ''));
            $session->fill([
                'reported_destination_account' => $account,
                'account_in_platform' => true,
                'current_step' => self::STEP_SESSION_ID,
                'bot_messages' => $messages,
            ])->save();

            return ['ok' => true, 'session' => $session->fresh(), 'payload' => $this->sessionPayload($session, $request)];
        }

        if ($step === self::STEP_SESSION_ID) {
            $sessionId = trim((string) $value);
            if (strlen($sessionId) < 4) {
                return ['ok' => false, 'message' => 'Bank session ID must be at least 4 characters.'];
            }

            $account = (string) $session->reported_destination_account;
            $evaluation = $this->payeeAccounts->evaluate($sessionId, $account);
            $messages[] = $this->userLine($sessionId);

            if (! $evaluation['account_on_session'] && $evaluation['payment_found']) {
                $messages[] = $this->botLine((string) config('support.intake_messages.account_mismatch', ''));
                $session->fill([
                    'payment_session_id' => $sessionId,
                    'account_on_session' => false,
                    'bot_messages' => $messages,
                    'current_step' => self::STEP_DESTINATION_ACCOUNT,
                ])->save();

                return ['ok' => false, 'message' => (string) config('support.intake_messages.account_mismatch', 'Account does not match session.')];
            }

            if (! $evaluation['payment_found']) {
                $messages[] = $this->botLine((string) config('support.intake_messages.session_not_found', ''));
            } else {
                $payment = $evaluation['payment'];
                $messages[] = $this->botLine($this->payeeAccounts->buildStatusMessage($payment));
                $session->payment_id = $payment?->id;
            }

            $whatsappEligible = (bool) $evaluation['whatsapp_eligible'];
            $session->fill([
                'payment_session_id' => $sessionId,
                'account_on_session' => (bool) $evaluation['account_on_session'],
                'whatsapp_eligible_at' => $whatsappEligible ? now() : null,
                'intake_status' => $whatsappEligible
                    ? SupportIntakeSession::STATUS_QUALIFIED
                    : SupportIntakeSession::STATUS_IN_PROGRESS,
            ]);

            $messages[] = $this->botLine((string) config('support.intake_messages.ask_name', ''));
            $session->fill([
                'current_step' => self::STEP_NAME,
                'bot_messages' => $messages,
            ])->save();

            return ['ok' => true, 'session' => $session->fresh(), 'payload' => $this->sessionPayload($session, $request)];
        }

        if ($step === self::STEP_NAME) {
            $name = trim((string) $value);
            if ($name === '') {
                return ['ok' => false, 'message' => 'Please enter your name.'];
            }
            $messages[] = $this->userLine($name);
            $messages[] = $this->botLine((string) config('support.intake_messages.ask_amount', ''));
            $session->fill([
                'visitor_name' => $name,
                'current_step' => self::STEP_AMOUNT,
                'bot_messages' => $messages,
            ])->save();

            return ['ok' => true, 'session' => $session->fresh(), 'payload' => $this->sessionPayload($session, $request)];
        }

        if ($step === self::STEP_AMOUNT) {
            $amount = round((float) $value, 2);
            if ($amount <= 0) {
                return ['ok' => false, 'message' => 'Please enter a valid amount.'];
            }
            $messages[] = $this->userLine('₦'.number_format($amount, 2));
            $messages[] = $this->botLine((string) config('support.intake_messages.ask_bank_from', ''));
            $session->fill([
                'payment_amount_reported' => $amount,
                'current_step' => self::STEP_BANK_FROM,
                'bot_messages' => $messages,
            ])->save();

            return ['ok' => true, 'session' => $session->fresh(), 'payload' => $this->sessionPayload($session, $request)];
        }

        if ($step === self::STEP_BANK_FROM) {
            $bank = trim((string) $value);
            if ($bank === '') {
                return ['ok' => false, 'message' => 'Please enter your bank name.'];
            }
            $messages[] = $this->userLine($bank);
            $messages[] = $this->botLine((string) config('support.intake_messages.ask_receipt', ''));
            $session->fill([
                'reported_destination_bank' => $bank,
                'current_step' => self::STEP_RECEIPT,
                'bot_messages' => $messages,
            ])->save();

            return ['ok' => true, 'session' => $session->fresh(), 'payload' => $this->sessionPayload($session, $request)];
        }

        if ($step === self::STEP_RECEIPT) {
            $skip = is_string($value) && strtolower(trim($value)) === 'skip';
            if (! $skip) {
                return ['ok' => false, 'message' => 'Upload a receipt image or type "skip".'];
            }
            $messages[] = $this->userLine('Skip receipt');
            return $this->advanceToContactMode($session, $messages, $request);
        }

        if ($step === self::STEP_CONTACT_MODE) {
            $mode = strtolower(trim((string) $value));
            $linkWallet = $mode === 'whatsapp' || $mode === 'wallet';
            if ($linkWallet && ! $session->isWhatsappEligible()) {
                return ['ok' => false, 'message' => (string) config('support.intake_messages.whatsapp_requires_verification', '')];
            }

            $messages[] = $this->userLine($linkWallet ? 'Link WhatsApp' : 'Browser chat only');
            $session->link_whatsapp_wallet = $linkWallet;

            if ($linkWallet) {
                if ($session->consumer_wallet_api_account_id) {
                    return $this->complete($session, $request, $messages);
                }
                $messages[] = $this->botLine((string) config('support.intake_messages.ask_phone', ''));
                $session->fill([
                    'current_step' => self::STEP_PHONE,
                    'bot_messages' => $messages,
                ])->save();

                return ['ok' => true, 'session' => $session->fresh(), 'payload' => $this->sessionPayload($session, $request)];
            }

            return $this->complete($session, $request, $messages);
        }

        if ($step === self::STEP_PHONE) {
            if (! is_array($value)) {
                return ['ok' => false, 'message' => 'Phone and country are required.'];
            }
            $phone = trim((string) ($value['phone'] ?? ''));
            $countryIso = strtoupper(substr(trim((string) ($value['country_iso'] ?? '')), 0, 2));
            if ($phone === '' || $countryIso === '') {
                return ['ok' => false, 'message' => 'Phone and country are required.'];
            }
            if (! $this->countries->isSupportedCountry($countryIso)) {
                return ['ok' => false, 'message' => 'Please select a supported country.'];
            }

            $ensured = $this->onboarding->ensureWalletFromPhone($phone, $countryIso);
            if (! $ensured['ok']) {
                return ['ok' => false, 'message' => $ensured['message'] ?? 'Could not link wallet.'];
            }

            /** @var WhatsappWallet $wallet */
            $wallet = $ensured['wallet'];
            $messages[] = $this->userLine($phone.' ('.$countryIso.')');

            $session->fill([
                'visitor_phone' => $wallet->phone_e164,
                'visitor_country' => $countryIso,
                'whatsapp_wallet_id' => $wallet->id,
            ]);

            return $this->complete($session, $request, $messages);
        }

        return ['ok' => false, 'message' => 'Unknown intake step.'];
    }

    /**
     * @return array{ok: bool, message?: string, session?: SupportIntakeSession, payload?: array<string, mixed>}
     */
    public function storeReceipt(SupportIntakeSession $session, UploadedFile $file, Request $request): array
    {
        if ($session->isTerminal()) {
            return ['ok' => false, 'message' => 'This intake session is already finished.'];
        }

        if ($session->current_step !== self::STEP_RECEIPT) {
            return ['ok' => false, 'message' => 'Receipt upload is not expected at this step.'];
        }

        $path = $file->store('support-receipts', 'local');
        $messages = is_array($session->bot_messages) ? $session->bot_messages : [];
        $messages[] = $this->userLine('Receipt uploaded');
        $session->payment_receipt_path = $path;

        return $this->advanceToContactMode($session, $messages, $request);
    }

    /**
     * @param  array<int, array{role: string, body: string}>  $messages
     * @return array{ok: bool, message?: string, session?: SupportIntakeSession, payload?: array<string, mixed>}
     */
    private function advanceToContactMode(SupportIntakeSession $session, array $messages, Request $request): array
    {
        $ask = (string) config('support.intake_messages.ask_contact_mode', '');
        $messages[] = $this->botLine($ask);
        $session->fill([
            'current_step' => self::STEP_CONTACT_MODE,
            'bot_messages' => $messages,
        ])->save();

        return ['ok' => true, 'session' => $session->fresh(), 'payload' => $this->sessionPayload($session, $request)];
    }

    /**
     * @param  array<int, array{role: string, body: string}>|null  $messages
     * @return array{ok: bool, message?: string, session?: SupportIntakeSession, payload?: array<string, mixed>}
     */
    public function complete(SupportIntakeSession $session, Request $request, ?array $messages = null): array
    {
        if ($session->support_ticket_id) {
            return [
                'ok' => true,
                'session' => $session,
                'payload' => $this->sessionPayload($session, $request),
            ];
        }

        if ($session->intake_status === SupportIntakeSession::STATUS_REJECTED_NON_PAYMENT
            || $session->intake_status === SupportIntakeSession::STATUS_REJECTED_NOT_OUR_ACCOUNT) {
            return ['ok' => false, 'message' => 'This issue cannot be escalated to a support ticket.'];
        }

        $messages = $messages ?? (is_array($session->bot_messages) ? $session->bot_messages : []);
        $messages[] = $this->botLine((string) config('support.intake_messages.ready_to_complete', ''));

        $wallet = null;
        if ($session->whatsapp_wallet_id) {
            $wallet = WhatsappWallet::query()->find($session->whatsapp_wallet_id);
        }

        if ($session->consumer_wallet_api_account_id && $session->link_whatsapp_wallet) {
            $account = ConsumerWalletApiAccount::query()->find($session->consumer_wallet_api_account_id);
            $wallet = $this->conversations->resolveWalletForAccount($account);
        }

        $linkWallet = (bool) $session->link_whatsapp_wallet;

        return DB::transaction(function () use ($session, $request, $messages, $wallet, $linkWallet) {
            $result = $this->conversations->startConversation([
                'channel' => $session->channel,
                'issue_type' => $session->issue_type ?? 'payment_pending_transfer',
                'payment_transaction_id' => $session->payment_session_id,
                'payment_amount_reported' => $session->payment_amount_reported,
                'name' => $session->visitor_name,
                'phone' => $session->visitor_phone,
                'country_iso' => $session->visitor_country,
                'wallet' => $wallet,
                'link_whatsapp_wallet' => $linkWallet,
                'consent_accepted' => true,
                'wallet_consent_accepted' => $linkWallet ? true : null,
                'first_message' => $this->buildTicketOpeningNote($session),
                'intake_status' => $session->intake_status === SupportIntakeSession::STATUS_QUALIFIED
                    ? SupportIntakeSession::STATUS_QUALIFIED
                    : SupportIntakeSession::STATUS_IN_PROGRESS,
                'reported_destination_account' => $session->reported_destination_account,
                'reported_destination_bank' => $session->reported_destination_bank,
                'whatsapp_eligible_at' => $session->whatsapp_eligible_at,
                'payment_receipt_path' => $session->payment_receipt_path,
                'account_on_session' => $session->account_on_session,
                'payment_id' => $session->payment_id,
                'skip_whatsapp_welcome' => ! $session->isWhatsappEligible(),
            ], $request);

            if (! $result['ok']) {
                return $result;
            }

            /** @var SupportTicket $ticket */
            $ticket = $result['ticket'];
            $publicToken = (string) $result['public_token'];

            $session->fill([
                'intake_status' => SupportIntakeSession::STATUS_COMPLETED,
                'current_step' => self::STEP_DONE,
                'support_ticket_id' => $ticket->id,
                'public_token' => $publicToken,
                'bot_messages' => $messages,
            ])->save();

            return [
                'ok' => true,
                'session' => $session->fresh(),
                'payload' => array_merge($this->sessionPayload($session, $request), [
                    'public_token' => $publicToken,
                    'ticket_id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                ]),
            ];
        });
    }

    private function buildTicketOpeningNote(SupportIntakeSession $session): string
    {
        $lines = ['[Support intake]'];
        if ($session->reported_destination_account) {
            $lines[] = 'Paid to account: '.$session->reported_destination_account;
        }
        if ($session->payment_session_id) {
            $lines[] = 'Bank session ID: '.$session->payment_session_id;
        }
        if ($session->payment_amount_reported) {
            $lines[] = 'Amount: '.$this->payments->formatMoney((float) $session->payment_amount_reported);
        }
        if ($session->reported_destination_bank) {
            $lines[] = 'Bank sent from: '.$session->reported_destination_bank;
        }
        $lines[] = 'Session matched account: '.($session->account_on_session ? 'yes' : 'no');
        $lines[] = 'WhatsApp eligible: '.($session->isWhatsappEligible() ? 'yes' : 'no');

        return implode("\n", $lines);
    }

    /**
     * @return array{role: string, body: string}
     */
    private function botLine(string $body): array
    {
        return ['role' => 'bot', 'body' => $body];
    }

    /**
     * @return array{role: string, body: string}
     */
    private function userLine(string $body): array
    {
        return ['role' => 'user', 'body' => $body];
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  array<int, array{role: string, body: string}>  $messages
     * @return array{ok: bool, session?: SupportIntakeSession, payload?: array<string, mixed>}
     */
    private function handleWrongAccount(
        SupportIntakeSession $session,
        Request $request,
        array $messages,
        string $account
    ): array {
        $record = $this->lockout->recordWrongAccount($request);
        $sessionAttempts = (int) $session->wrong_account_attempts + 1;

        $messages[] = $this->userLine($account);
        $messages[] = $this->botLine((string) config('support.intake_messages.not_our_account', ''));

        if ($record['locked'] || $record['just_locked']) {
            $lockedUntil = isset($record['locked_until'])
                ? Carbon::parse($record['locked_until'])
                : now()->addMinutes($this->lockout->lockoutMinutes());

            $messages[] = $this->botLine($this->lockoutMessage($lockedUntil->toIso8601String()));

            $session->fill([
                'reported_destination_account' => $account,
                'account_in_platform' => false,
                'wrong_account_attempts' => max($sessionAttempts, $record['attempts']),
                'locked_until' => $lockedUntil,
                'intake_status' => SupportIntakeSession::STATUS_LOCKED_OUT,
                'current_step' => self::STEP_DONE,
                'last_visitor_ip' => $request->ip(),
                'bot_messages' => $messages,
            ])->save();

            return ['ok' => true, 'session' => $session->fresh(), 'payload' => $this->sessionPayload($session, $request)];
        }

        $remaining = $this->lockout->remainingAttempts($record['attempts']);
        $retry = (string) config('support.intake_messages.not_our_account_retry', '');
        if ($remaining > 0 && $retry !== '') {
            $attemptLabel = $remaining === 1 ? 'attempt' : 'attempts';
            $messages[] = $this->botLine($retry.' ('.$remaining.' '.$attemptLabel.' left).');
        }

        $session->fill([
            'reported_destination_account' => null,
            'account_in_platform' => false,
            'wrong_account_attempts' => $sessionAttempts,
            'intake_status' => SupportIntakeSession::STATUS_IN_PROGRESS,
            'current_step' => self::STEP_DESTINATION_ACCOUNT,
            'last_visitor_ip' => $request->ip(),
            'bot_messages' => $messages,
        ])->save();

        return ['ok' => true, 'session' => $session->fresh(), 'payload' => $this->sessionPayload($session, $request)];
    }

    /**
     * @param  array<int, array{role: string, body: string}>  $messages
     * @return array{ok: bool, session?: SupportIntakeSession, payload?: array<string, mixed>}
     */
    private function restartIntake(SupportIntakeSession $session, Request $request, array $messages): array
    {
        $messages[] = $this->userLine('Restart');
        $messages[] = $this->botLine((string) config('support.intake_messages.ask_payment_issue', ''));

        $session->fill([
            'intake_status' => SupportIntakeSession::STATUS_IN_PROGRESS,
            'current_step' => self::STEP_PAYMENT_ISSUE,
            'is_payment_issue' => null,
            'issue_type' => null,
            'reported_destination_account' => null,
            'reported_destination_bank' => null,
            'payment_session_id' => null,
            'payment_amount_reported' => null,
            'visitor_name' => null,
            'payment_id' => null,
            'account_on_session' => false,
            'account_in_platform' => false,
            'whatsapp_eligible_at' => null,
            'payment_receipt_path' => null,
            'link_whatsapp_wallet' => false,
            'last_visitor_ip' => $request->ip(),
            'bot_messages' => $messages,
        ])->save();

        return ['ok' => true, 'session' => $session->fresh(), 'payload' => $this->sessionPayload($session, $request)];
    }

    private function lockoutMessage(?string $lockedUntilIso): string
    {
        $template = (string) config('support.intake_messages.locked_out', 'Please wait :minutes minutes.');
        $minutes = (string) $this->lockout->lockoutMinutes();

        if ($lockedUntilIso) {
            $until = Carbon::parse($lockedUntilIso);
            $mins = max(1, (int) now()->diffInMinutes($until, false));

            return str_replace(':minutes', (string) $mins, $template);
        }

        return str_replace(':minutes', $minutes, $template);
    }

    public function sessionPayload(SupportIntakeSession $session, ?Request $request = null): array
    {
        $max = $this->lockout->maxWrongAccountAttempts();
        $attempts = (int) $session->wrong_account_attempts;
        $clientLock = $request ? $this->lockout->status($request) : ['locked' => false, 'attempts' => 0];
        $isLocked = $session->isLockedOut() || ($clientLock['locked'] ?? false);
        $lockedUntil = $session->locked_until?->toIso8601String() ?? ($clientLock['locked_until'] ?? null);

        return [
            'intake_token' => $session->intake_token,
            'channel' => $session->channel,
            'intake_status' => $session->intake_status,
            'current_step' => $session->current_step,
            'whatsapp_eligible' => $session->isWhatsappEligible(),
            'whatsapp_eligible_at' => $session->whatsapp_eligible_at?->toIso8601String(),
            'account_on_session' => (bool) $session->account_on_session,
            'account_in_platform' => (bool) $session->account_in_platform,
            'is_terminal' => $session->isTerminal() || $isLocked,
            'is_locked' => $isLocked,
            'locked_until' => $lockedUntil,
            'wrong_account_attempts' => $attempts,
            'wrong_account_attempts_remaining' => $this->lockout->remainingAttempts(
                max($attempts, (int) ($clientLock['attempts'] ?? 0))
            ),
            'can_retry_account' => $session->canRetryDestinationAccount(),
            'can_restart' => $session->intake_status === SupportIntakeSession::STATUS_IN_PROGRESS
                && $session->current_step === self::STEP_DESTINATION_ACCOUNT,
            'messages' => $session->bot_messages ?? [],
            'public_token' => $session->public_token,
            'ticket_id' => $session->support_ticket_id,
            'link_whatsapp_wallet' => (bool) $session->link_whatsapp_wallet,
            'allowed_contact_modes' => $session->isWhatsappEligible()
                ? ['browser', 'whatsapp']
                : ['browser'],
            'max_wrong_account_attempts' => $max,
        ];
    }
}
