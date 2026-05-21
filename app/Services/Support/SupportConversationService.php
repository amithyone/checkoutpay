<?php

namespace App\Services\Support;

use App\Models\ConsumerWalletApiAccount;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\WhatsappWallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SupportConversationService
{
    public function __construct(
        private SupportWalletOnboardingService $onboarding,
        private SupportCountryOptionsService $countries,
        private SupportIssueOptionsService $issues,
        private SupportPaymentLookupService $payments,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{ok: bool, message?: string, ticket?: SupportTicket, public_token?: string}
     */
    public function startConversation(array $input, Request $request): array
    {
        $channel = (string) ($input['channel'] ?? SupportTicket::CHANNEL_CHECKOUT_WEB);
        if (! in_array($channel, SupportTicket::publicChannels(), true)) {
            return ['ok' => false, 'message' => 'Invalid support channel.'];
        }

        if (empty($input['consent_accepted'])) {
            return ['ok' => false, 'message' => 'You must accept the chat terms to continue.'];
        }

        $issueType = isset($input['issue_type']) ? trim((string) $input['issue_type']) : null;
        if ($issueType === '') {
            $issueType = null;
        }
        if ($issueType !== null && ! $this->issues->isValidIssueType($issueType)) {
            return ['ok' => false, 'message' => 'Please choose a valid support topic.'];
        }

        $payment = null;
        $paymentTransactionId = null;
        $paymentAmountReported = null;
        $issueMeta = $this->issues->metaFor($issueType);

        if ($issueType !== null && $this->issues->requiresPayment($issueType)) {
            $paymentTransactionId = trim((string) ($input['payment_transaction_id'] ?? $input['transaction_id'] ?? ''));
            if ($paymentTransactionId === '') {
                return ['ok' => false, 'message' => 'Bank session ID is required for this issue type.'];
            }
            $amountRaw = $input['payment_amount_reported'] ?? $input['amount_paid'] ?? null;
            if ($amountRaw === null || $amountRaw === '') {
                return ['ok' => false, 'message' => 'Please enter the amount you transferred.'];
            }
            $paymentAmountReported = round((float) $amountRaw, 2);
            if ($paymentAmountReported <= 0) {
                return ['ok' => false, 'message' => 'Please enter a valid amount.'];
            }

            $lookup = $this->payments->lookup($paymentTransactionId);
            if ($lookup['ok'] && isset($lookup['payment'])) {
                $payment = $lookup['payment'];
            }
        }

        $linkWallet = filter_var($input['link_whatsapp_wallet'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $wallet = $input['wallet'] ?? null;
        $visitorCountry = null;
        $visitorPhone = null;

        if ($linkWallet) {
            if (empty($input['wallet_consent_accepted'])) {
                return ['ok' => false, 'message' => 'You must accept the WhatsApp wallet terms to link your number.'];
            }

            if (! $wallet instanceof WhatsappWallet) {
                $phone = trim((string) ($input['phone'] ?? ''));
                $countryIso = strtoupper(substr(trim((string) ($input['country_iso'] ?? '')), 0, 2));
                if ($phone === '') {
                    return ['ok' => false, 'message' => 'WhatsApp number is required when linking a wallet.'];
                }
                if ($countryIso === '' || ! $this->countries->isSupportedCountry($countryIso)) {
                    return ['ok' => false, 'message' => 'Please select a supported country.'];
                }
                $ensured = $this->onboarding->ensureWalletFromPhone($phone, $countryIso);
                if (! $ensured['ok']) {
                    return ['ok' => false, 'message' => $ensured['message'] ?? 'Could not link wallet.'];
                }
                $wallet = $ensured['wallet'];
                $visitorCountry = $countryIso;
                $visitorPhone = $wallet->phone_e164;
            } else {
                $visitorPhone = $wallet->phone_e164;
                $visitorCountry = $this->countryFromE164($visitorPhone);
            }
        }

        $userNote = trim((string) ($input['first_message'] ?? ''));
        $firstMessage = $userNote;

        if ($issueType !== null && $this->issues->requiresPayment($issueType) && $paymentTransactionId !== null) {
            $issueLabel = $issueMeta['label'] ?? $issueType;
            $firstMessage = $this->payments->buildPaymentIssueMessage(
                $issueLabel,
                $paymentTransactionId,
                $paymentAmountReported,
                $payment,
                $userNote !== '' ? $userNote : null
            );
        }

        $priority = SupportTicket::PRIORITY_MEDIUM;
        if ($issueMeta && isset($issueMeta['priority'])) {
            $priority = match ($issueMeta['priority']) {
                'high', 'urgent' => SupportTicket::PRIORITY_HIGH,
                'low' => SupportTicket::PRIORITY_LOW,
                default => SupportTicket::PRIORITY_MEDIUM,
            };
        }

        $subjectPrefix = $issueMeta['subject_prefix'] ?? 'Live support';
        if ($paymentTransactionId !== null) {
            $subject = $subjectPrefix.': '.$paymentTransactionId;
            if ($paymentAmountReported !== null) {
                $subject .= ' · '.$this->payments->formatMoney($paymentAmountReported);
            }
        } elseif ($firstMessage !== '') {
            $subject = Str::limit($firstMessage, 80);
        } else {
            $subject = $issueMeta ? ($issueMeta['label'] ?? 'Live support chat') : 'Live support chat';
        }
        $subject = Str::limit($subject, 120);

        return DB::transaction(function () use (
            $channel,
            $wallet,
            $linkWallet,
            $input,
            $request,
            $firstMessage,
            $subject,
            $visitorCountry,
            $visitorPhone,
            $issueType,
            $payment,
            $paymentTransactionId,
            $paymentAmountReported,
            $priority,
            $userNote
        ) {
            $token = (string) Str::uuid();

            $ticket = SupportTicket::create([
                'channel' => $channel,
                'issue_type' => $issueType,
                'payment_id' => $payment?->id,
                'payment_transaction_id' => $paymentTransactionId,
                'payment_amount_reported' => $paymentAmountReported,
                'business_id' => $payment?->business_id,
                'whatsapp_wallet_id' => $wallet?->id,
                'wallet_linked' => $linkWallet && $wallet !== null,
                'visitor_country' => $visitorCountry,
                'visitor_name' => isset($input['name']) ? trim((string) $input['name']) : null,
                'visitor_email' => isset($input['email']) ? trim((string) $input['email']) : null,
                'visitor_phone' => $visitorPhone,
                'public_token' => $token,
                'subject' => $subject,
                'message' => $firstMessage !== '' ? $firstMessage : 'Support conversation started.',
                'priority' => $priority,
                'status' => SupportTicket::STATUS_OPEN,
                'last_message_at' => now(),
                'last_visitor_ip' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500),
            ]);

            if ($firstMessage !== '' && ($userNote !== '' || $issueType !== null)) {
                $this->createVisitorReply($ticket, $firstMessage);
            }

            if ($wallet && $ticket->wallet_onboarding_sent_at === null) {
                $sendWelcome = $channel !== SupportTicket::CHANNEL_CHECKOUTNOW_APP
                    && config('support.send_whatsapp_welcome_on_web', true);

                if ($sendWelcome && $this->onboarding->sendWelcomeMessage($wallet)) {
                    $ticket->update(['wallet_onboarding_sent_at' => now()]);
                } elseif (! $sendWelcome || $this->onboarding->alreadySentSupportWelcome($wallet)) {
                    $ticket->update(['wallet_onboarding_sent_at' => now()]);
                }
            }

            return [
                'ok' => true,
                'ticket' => $ticket->fresh(),
                'public_token' => $token,
            ];
        });
    }

    private function countryFromE164(?string $e164): ?string
    {
        if (! $e164) {
            return null;
        }
        foreach ($this->countries->supportedCountries() as $row) {
            $dial = (string) $row['dial'];
            if ($dial !== '' && str_starts_with($e164, $dial)) {
                return $row['iso'];
            }
        }

        return null;
    }

    public function resolveWalletForAccount(?ConsumerWalletApiAccount $account): ?WhatsappWallet
    {
        if (! $account || ! $account->whatsapp_wallet_id) {
            return null;
        }

        return WhatsappWallet::query()->find($account->whatsapp_wallet_id);
    }

    public function findByPublicToken(string $token): ?SupportTicket
    {
        return SupportTicket::query()
            ->where('public_token', $token)
            ->whereIn('channel', SupportTicket::publicChannels())
            ->first();
    }

    /**
     * @return array{ok: bool, message?: string, reply?: SupportTicketReply}
     */
    public function addVisitorMessage(SupportTicket $ticket, string $message): array
    {
        $message = trim($message);
        if ($message === '') {
            return ['ok' => false, 'message' => 'Message cannot be empty.'];
        }

        $reply = $this->createVisitorReply($ticket, $message);

        return ['ok' => true, 'reply' => $reply];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listMessagesForVisitor(SupportTicket $ticket, ?int $afterId = null): array
    {
        $query = $ticket->publicReplies()->orderBy('id');

        if ($afterId !== null && $afterId > 0) {
            $query->where('id', '>', $afterId);
        }

        $replies = $query->get();

        if ($ticket->visitor_unread_count > 0) {
            $ticket->update(['visitor_unread_count' => 0]);
        }

        $initial = [];
        if ($afterId === null || $afterId === 0) {
            $initial[] = $this->formatInitialMessage($ticket);
        }

        return array_merge(
            $initial,
            $replies->map(fn (SupportTicketReply $r) => $this->formatReply($r))->all()
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listMessagesForAdmin(SupportTicket $ticket, ?int $afterId = null, bool $markRead = true): array
    {
        $query = $ticket->replies()->where('is_internal_note', false)->orderBy('id');

        if ($afterId !== null && $afterId > 0) {
            $query->where('id', '>', $afterId);
        }

        if ($markRead && $ticket->admin_unread_count > 0) {
            $ticket->update(['admin_unread_count' => 0]);
        }

        $rows = [];
        if ($afterId === null || $afterId === 0) {
            $rows[] = $this->formatInitialMessage($ticket);
        }

        foreach ($query->get() as $reply) {
            $rows[] = $this->formatReply($reply);
        }

        return $rows;
    }

    /**
     * @return array{ok: bool, message?: string, reply?: SupportTicketReply}
     */
    public function addAdminReply(SupportTicket $ticket, string $message, bool $isInternal = false): array
    {
        $message = trim($message);
        if ($message === '') {
            return ['ok' => false, 'message' => 'Message cannot be empty.'];
        }

        $reply = SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => auth('admin')->id(),
            'user_type' => 'admin',
            'message' => $message,
            'is_internal_note' => $isInternal,
        ]);

        if (! $isInternal && $ticket->isPublicChannel()) {
            $ticket->increment('visitor_unread_count');
        }

        $this->touchTicketAfterMessage($ticket, 'admin');

        return ['ok' => true, 'reply' => $reply];
    }

    private function createVisitorReply(SupportTicket $ticket, string $message): SupportTicketReply
    {
        $reply = SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => null,
            'user_type' => 'visitor',
            'message' => $message,
            'is_internal_note' => false,
        ]);

        $ticket->increment('admin_unread_count');
        $this->touchTicketAfterMessage($ticket, 'visitor');

        if (in_array($ticket->status, [SupportTicket::STATUS_RESOLVED, SupportTicket::STATUS_CLOSED], true)) {
            $ticket->update(['status' => SupportTicket::STATUS_OPEN]);
        }

        return $reply;
    }

    private function touchTicketAfterMessage(SupportTicket $ticket, string $lastSpeaker): void
    {
        $updates = ['last_message_at' => now()];

        if ($ticket->status === SupportTicket::STATUS_OPEN && $lastSpeaker === 'admin') {
            $updates['status'] = SupportTicket::STATUS_IN_PROGRESS;
        }

        $ticket->update($updates);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatInitialMessage(SupportTicket $ticket): array
    {
        return [
            'id' => 0,
            'user_type' => 'visitor',
            'message' => $ticket->message,
            'is_internal_note' => false,
            'created_at' => $ticket->created_at?->toIso8601String(),
            'created_at_human' => $ticket->created_at?->diffForHumans(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatReply(SupportTicketReply $reply): array
    {
        return [
            'id' => $reply->id,
            'user_type' => $reply->user_type,
            'message' => $reply->message,
            'is_internal_note' => (bool) $reply->is_internal_note,
            'created_at' => $reply->created_at?->toIso8601String(),
            'created_at_human' => $reply->created_at?->diffForHumans(),
        ];
    }
}
