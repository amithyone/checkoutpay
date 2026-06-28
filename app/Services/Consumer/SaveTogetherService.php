<?php

namespace App\Services\Consumer;

use App\Models\WalletSaveTogetherContribution;
use App\Models\WalletSaveTogetherMember;
use App\Models\WalletSaveTogetherPot;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\Consumer\ConsumerWalletPushNotificationService;
use App\Services\Whatsapp\EvolutionWhatsAppClient;
use App\Services\Whatsapp\PhoneNormalizer;
use App\Services\Whatsapp\WhatsappEvolutionConfigResolver;
use App\Services\Whatsapp\WhatsappWalletMoneyFormatter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class SaveTogetherService
{
    public function __construct(
        private ConsumerWalletPushNotificationService $consumerPush,
        private EvolutionWhatsAppClient $whatsappClient,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('consumer_wallet.save_together_enabled', true);
    }

    /**
     * @param  list<string>  $memberPhoneInputs
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function create(
        WhatsappWallet $creatorWallet,
        string $title,
        float $targetAmount,
        array $memberPhoneInputs,
        string $completionMode,
        ?Carbon $deadlineAt = null,
        ?string $note = null,
    ): array {
        if (! $this->isEnabled()) {
            return ['ok' => false, 'message' => 'Save Together is not available right now.'];
        }

        $title = trim($title);
        if ($title === '') {
            return ['ok' => false, 'message' => 'Enter a title for this save.'];
        }

        $minTarget = (float) config('consumer_wallet.save_together_min_target', 100);
        if ($targetAmount < $minTarget) {
            return ['ok' => false, 'message' => 'Target must be at least ₦'.number_format($minTarget, 2).'.'];
        }

        $completionMode = strtolower(trim($completionMode));
        if (! in_array($completionMode, [WalletSaveTogetherPot::MODE_FULL_CONTRIBUTION, WalletSaveTogetherPot::MODE_TIME_DEADLINE], true)) {
            return ['ok' => false, 'message' => 'Invalid completion mode.'];
        }

        if ($completionMode === WalletSaveTogetherPot::MODE_TIME_DEADLINE) {
            if ($deadlineAt === null || $deadlineAt->isPast()) {
                return ['ok' => false, 'message' => 'Choose a future deadline.'];
            }
        }

        $creatorPhone = (string) $creatorWallet->phone_e164;
        $phones = [];
        foreach ($memberPhoneInputs as $input) {
            $phone = PhoneNormalizer::canonicalNgE164Digits((string) $input)
                ?? PhoneNormalizer::canonicalInternationalWalletRecipientDigits(
                    PhoneNormalizer::digitsOnly((string) $input) ?? (string) $input
                );
            if ($phone === null || $phone === '') {
                return ['ok' => false, 'message' => 'Invalid phone number in member list.'];
            }
            if ($phone === $creatorPhone) {
                continue;
            }
            $phones[$phone] = true;
        }

        $memberCount = count($phones) + 1;
        $minMembers = max(2, (int) config('consumer_wallet.save_together_min_members', 2));
        $maxMembers = max($minMembers, (int) config('consumer_wallet.save_together_max_members', 20));
        if ($memberCount < $minMembers || $memberCount > $maxMembers) {
            return ['ok' => false, 'message' => "Group must have {$minMembers}–{$maxMembers} members (including you)."];
        }

        $perShare = round($targetAmount / $memberCount, 2);
        if ($perShare < 1) {
            return ['ok' => false, 'message' => 'Target is too small for this group size.'];
        }

        $pot = DB::transaction(function () use ($creatorWallet, $title, $targetAmount, $perShare, $completionMode, $deadlineAt, $note, $creatorPhone, $phones) {
            $pot = WalletSaveTogetherPot::query()->create([
                'public_id' => (string) Str::uuid(),
                'creator_wallet_id' => $creatorWallet->id,
                'title' => Str::limit($title, 120, ''),
                'target_amount' => round($targetAmount, 2),
                'per_member_share' => $perShare,
                'total_contributed' => 0,
                'completion_mode' => $completionMode,
                'deadline_at' => $deadlineAt,
                'status' => WalletSaveTogetherPot::STATUS_COLLECTING,
                'currency' => 'NGN',
                'meta' => $note !== null && trim($note) !== '' ? ['note' => Str::limit(trim($note), 140, '')] : null,
            ]);

            WalletSaveTogetherMember::query()->create([
                'pot_id' => $pot->id,
                'wallet_id' => $creatorWallet->id,
                'phone_e164' => $creatorPhone,
                'display_name' => $creatorWallet->displayName(),
                'role' => WalletSaveTogetherMember::ROLE_CREATOR,
                'share_target' => $perShare,
                'status' => WalletSaveTogetherMember::STATUS_INVITED,
                'invited_at' => now(),
            ]);

            foreach (array_keys($phones) as $phone) {
                $wallet = WhatsappWallet::query()
                    ->where('phone_e164', $phone)
                    ->where('status', WhatsappWallet::STATUS_ACTIVE)
                    ->first();

                WalletSaveTogetherMember::query()->create([
                    'pot_id' => $pot->id,
                    'wallet_id' => $wallet?->id,
                    'phone_e164' => $phone,
                    'display_name' => $this->displayNameForPhone($wallet, $phone),
                    'role' => WalletSaveTogetherMember::ROLE_MEMBER,
                    'share_target' => $perShare,
                    'status' => WalletSaveTogetherMember::STATUS_INVITED,
                    'invited_at' => now(),
                ]);
            }

            return $pot;
        });

        $this->notifyMembersInvited($pot->fresh(['members', 'creatorWallet']));

        return [
            'ok' => true,
            'message' => 'Save Together pot created. Invites sent.',
            'data' => $this->serializePot($pot->fresh(['members'])),
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function contribute(WhatsappWallet $wallet, string $publicId, float $amount): array
    {
        if ($amount < (float) config('consumer_wallet.save_together_min_contribution', 1)) {
            return ['ok' => false, 'message' => 'Invalid amount.'];
        }

        $pot = $this->findPotByPublicId($publicId);
        if ($pot === null || ! $pot->isCollecting()) {
            return ['ok' => false, 'message' => 'Save Together pot not found or no longer accepting contributions.'];
        }

        $member = $this->findMemberForWallet($pot, $wallet);
        if ($member === null) {
            return ['ok' => false, 'message' => 'You are not a member of this pot.'];
        }

        if (in_array($member->status, [WalletSaveTogetherMember::STATUS_DECLINED, WalletSaveTogetherMember::STATUS_LEFT], true)) {
            return ['ok' => false, 'message' => 'You cannot contribute to this pot.'];
        }

        if ($member->hasCompletedShare()) {
            return ['ok' => false, 'message' => 'You have already completed your share.'];
        }

        $remaining = $member->remainingShare();
        $amount = round($amount, 2);
        if ($amount > $remaining + 0.0001) {
            return ['ok' => false, 'message' => 'Amount exceeds your remaining share (₦'.number_format($remaining, 2).' left).'];
        }

        try {
            DB::transaction(function () use ($wallet, $pot, $member, $amount) {
                $lockedWallet = WhatsappWallet::query()->lockForUpdate()->find($wallet->id);
                if (! $lockedWallet) {
                    throw new \RuntimeException('wallet_missing');
                }

                $lockedWallet->resetDailyTransferIfNeeded();
                $debitCheck = $lockedWallet->canDebit($amount);
                if (! $debitCheck['ok']) {
                    throw new \RuntimeException($debitCheck['message'] ?? 'Insufficient balance.');
                }

                $newBal = round((float) $lockedWallet->balance - $amount, 2);
                $lockedWallet->balance = $newBal;
                $lockedWallet->daily_transfer_total = round((float) $lockedWallet->daily_transfer_total + $amount, 2);
                $lockedWallet->daily_transfer_for_date = now()->toDateString();
                $lockedWallet->save();

                $txn = WhatsappWalletTransaction::query()->create([
                    'whatsapp_wallet_id' => $lockedWallet->id,
                    'sender_name' => $lockedWallet->normalizedSenderName(),
                    'type' => WhatsappWalletTransaction::TYPE_SAVE_TOGETHER_CONTRIBUTE,
                    'ledger_scope' => ConsumerWalletTransactionScope::SCOPE_PERSONAL,
                    'amount' => $amount,
                    'balance_after' => $newBal,
                    'counterparty_account_name' => $pot->title,
                    'meta' => [
                        'channel' => 'consumer_api',
                        'save_together_pot_id' => (string) $pot->public_id,
                    ],
                ]);

                $lockedMember = WalletSaveTogetherMember::query()->lockForUpdate()->find($member->id);
                $lockedPot = WalletSaveTogetherPot::query()->lockForUpdate()->find($pot->id);
                if (! $lockedMember || ! $lockedPot) {
                    throw new \RuntimeException('pot_missing');
                }

                WalletSaveTogetherContribution::query()->create([
                    'pot_id' => $lockedPot->id,
                    'member_id' => $lockedMember->id,
                    'amount' => $amount,
                    'kind' => WalletSaveTogetherContribution::KIND_CONTRIBUTE,
                    'whatsapp_wallet_transaction_id' => $txn->id,
                ]);

                if ($lockedMember->first_contributed_at === null) {
                    $lockedMember->first_contributed_at = now();
                }
                if ($lockedMember->status === WalletSaveTogetherMember::STATUS_INVITED) {
                    $lockedMember->status = WalletSaveTogetherMember::STATUS_ACTIVE;
                }
                $lockedMember->contributed_amount = round((float) $lockedMember->contributed_amount + $amount, 2);
                if ($lockedMember->hasCompletedShare()) {
                    $lockedMember->status = WalletSaveTogetherMember::STATUS_COMPLETED_SHARE;
                    $lockedMember->share_completed_at = now();
                }
                if ($lockedMember->wallet_id === null) {
                    $lockedMember->wallet_id = $lockedWallet->id;
                }
                $lockedMember->save();

                $lockedPot->total_contributed = round((float) $lockedPot->total_contributed + $amount, 2);
                $lockedPot->save();
            });
        } catch (\Throwable $e) {
            Log::warning('save_together.contribute_failed', ['error' => $e->getMessage(), 'pot' => $publicId]);

            $msg = $e->getMessage();
            if (str_starts_with($msg, 'Tier 1') || $msg === 'Insufficient balance.') {
                return ['ok' => false, 'message' => $msg];
            }

            return ['ok' => false, 'message' => 'Contribution failed. Check balance and try again.'];
        }

        $pot = $pot->fresh(['members']);
        if ($this->allActiveMembersCompleted($pot)) {
            $this->unlockPot($pot);
            $pot = $pot->fresh(['members']);
        }

        $member = $this->findMemberForWallet($pot, $wallet);

        return [
            'ok' => true,
            'message' => $member && $member->hasCompletedShare()
                ? 'Share complete. '.($pot->isUnlocked() ? 'Pot unlocked — you can withdraw when ready.' : 'Waiting for others.')
                : 'Contribution saved.',
            'data' => $this->serializePot($pot, $wallet),
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function withdraw(WhatsappWallet $wallet, string $publicId, ?float $amount = null): array
    {
        $pot = $this->findPotByPublicId($publicId);
        if ($pot === null || ! $pot->isUnlocked()) {
            return ['ok' => false, 'message' => 'Pot is not unlocked yet.'];
        }

        $member = $this->findMemberForWallet($pot, $wallet);
        if ($member === null) {
            return ['ok' => false, 'message' => 'You are not a member of this pot.'];
        }

        $refundable = $member->refundableAmount();
        if ($refundable < 0.01) {
            return ['ok' => false, 'message' => 'Nothing left to withdraw.'];
        }

        $amount = $amount === null ? $refundable : round($amount, 2);
        if ($amount < 0.01 || $amount > $refundable + 0.0001) {
            return ['ok' => false, 'message' => 'Invalid withdraw amount.'];
        }

        try {
            DB::transaction(function () use ($wallet, $pot, $member, $amount) {
                $lockedWallet = WhatsappWallet::query()->lockForUpdate()->find($wallet->id);
                if (! $lockedWallet) {
                    throw new \RuntimeException('wallet_missing');
                }

                $lockedMember = WalletSaveTogetherMember::query()->lockForUpdate()->find($member->id);
                $lockedPot = WalletSaveTogetherPot::query()->lockForUpdate()->find($pot->id);
                if (! $lockedMember || ! $lockedPot) {
                    throw new \RuntimeException('pot_missing');
                }

                $refundableNow = $lockedMember->refundableAmount();
                if ($amount > $refundableNow + 0.0001) {
                    throw new \RuntimeException('Invalid withdraw amount.');
                }

                if ((float) $lockedPot->total_contributed + 0.0001 < $amount) {
                    throw new \RuntimeException('Escrow mismatch.');
                }

                $newBal = round((float) $lockedWallet->balance + $amount, 2);
                $lockedWallet->balance = $newBal;
                $lockedWallet->save();

                $txn = WhatsappWalletTransaction::query()->create([
                    'whatsapp_wallet_id' => $lockedWallet->id,
                    'sender_name' => $lockedWallet->normalizedSenderName(),
                    'type' => WhatsappWalletTransaction::TYPE_SAVE_TOGETHER_WITHDRAW,
                    'ledger_scope' => ConsumerWalletTransactionScope::SCOPE_PERSONAL,
                    'amount' => $amount,
                    'balance_after' => $newBal,
                    'counterparty_account_name' => $pot->title,
                    'meta' => [
                        'channel' => 'consumer_api',
                        'save_together_pot_id' => (string) $pot->public_id,
                    ],
                ]);

                WalletSaveTogetherContribution::query()->create([
                    'pot_id' => $lockedPot->id,
                    'member_id' => $lockedMember->id,
                    'amount' => $amount,
                    'kind' => WalletSaveTogetherContribution::KIND_WITHDRAW,
                    'whatsapp_wallet_transaction_id' => $txn->id,
                ]);

                $lockedMember->withdrawn_amount = round((float) $lockedMember->withdrawn_amount + $amount, 2);
                if ($lockedMember->withdrawn_at === null) {
                    $lockedMember->withdrawn_at = now();
                }
                $lockedMember->save();

                $lockedPot->total_contributed = round((float) $lockedPot->total_contributed - $amount, 2);
                if ($this->allMembersFullyWithdrawn($lockedPot)) {
                    $lockedPot->status = WalletSaveTogetherPot::STATUS_CLOSED;
                    $lockedPot->closed_at = now();
                }
                $lockedPot->save();
            });
        } catch (\Throwable $e) {
            Log::warning('save_together.withdraw_failed', ['error' => $e->getMessage(), 'pot' => $publicId]);

            return ['ok' => false, 'message' => 'Withdraw failed. Try again.'];
        }

        return [
            'ok' => true,
            'message' => 'Withdrawn to your wallet.',
            'data' => $this->serializePot($pot->fresh(['members']), $wallet),
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function decline(WhatsappWallet $wallet, string $publicId): array
    {
        $pot = $this->findPotByPublicId($publicId);
        if ($pot === null || ! $pot->isCollecting()) {
            return ['ok' => false, 'message' => 'Pot not found or no longer open.'];
        }

        $member = $this->findMemberForWallet($pot, $wallet);
        if ($member === null) {
            return ['ok' => false, 'message' => 'You are not invited to this pot.'];
        }

        if ((float) $member->contributed_amount > 0) {
            return ['ok' => false, 'message' => 'You cannot decline after contributing.'];
        }

        if ($member->role === WalletSaveTogetherMember::ROLE_CREATOR) {
            return ['ok' => false, 'message' => 'Creator cannot decline their own pot.'];
        }

        $member->status = WalletSaveTogetherMember::STATUS_DECLINED;
        $member->save();

        $pot = $pot->fresh(['members']);
        if ($this->allActiveMembersCompleted($pot)) {
            $this->unlockPot($pot);
        }

        return [
            'ok' => true,
            'message' => 'You declined this Save Together invite.',
            'data' => $this->serializePot($pot->fresh(['members']), $wallet),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForWallet(WhatsappWallet $wallet): array
    {
        $phone = (string) $wallet->phone_e164;
        $potIds = WalletSaveTogetherMember::query()
            ->where('phone_e164', $phone)
            ->whereNotIn('status', [WalletSaveTogetherMember::STATUS_DECLINED])
            ->pluck('pot_id');

        return WalletSaveTogetherPot::query()
            ->whereIn('id', $potIds)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (WalletSaveTogetherPot $pot) => $this->serializePot($pot, $wallet))
            ->all();
    }

    public function findPotByPublicId(string $publicId): ?WalletSaveTogetherPot
    {
        return WalletSaveTogetherPot::query()->where('public_id', $publicId)->first();
    }

    public function processDeadlines(): int
    {
        $count = 0;
        WalletSaveTogetherPot::query()
            ->where('status', WalletSaveTogetherPot::STATUS_COLLECTING)
            ->where('completion_mode', WalletSaveTogetherPot::MODE_TIME_DEADLINE)
            ->whereNotNull('deadline_at')
            ->where('deadline_at', '<=', now())
            ->orderBy('id')
            ->chunkById(50, function ($pots) use (&$count) {
                foreach ($pots as $pot) {
                    $this->unlockPot($pot);
                    $count++;
                }
            });

        return $count;
    }

    public function unlockPot(WalletSaveTogetherPot $pot): void
    {
        if (! $pot->isCollecting()) {
            return;
        }

        $pot->status = WalletSaveTogetherPot::STATUS_UNLOCKED;
        $pot->unlocked_at = now();
        $pot->save();

        $this->notifyPotUnlocked($pot->fresh(['members', 'creatorWallet']));
    }

    private function allActiveMembersCompleted(WalletSaveTogetherPot $pot): bool
    {
        $active = $pot->members->filter(fn (WalletSaveTogetherMember $m) => $m->countsTowardUnlock());
        if ($active->isEmpty()) {
            return false;
        }

        return $active->every(fn (WalletSaveTogetherMember $m) => $m->hasCompletedShare());
    }

    private function allMembersFullyWithdrawn(WalletSaveTogetherPot $pot): bool
    {
        $pot->loadMissing('members');
        $withContributions = $pot->members->filter(fn (WalletSaveTogetherMember $m) => (float) $m->contributed_amount > 0);

        if ($withContributions->isEmpty()) {
            return (float) $pot->total_contributed < 0.01;
        }

        return $withContributions->every(fn (WalletSaveTogetherMember $m) => $m->refundableAmount() < 0.01);
    }

    private function findMemberForWallet(WalletSaveTogetherPot $pot, WhatsappWallet $wallet): ?WalletSaveTogetherMember
    {
        return WalletSaveTogetherMember::query()
            ->where('pot_id', $pot->id)
            ->where('phone_e164', (string) $wallet->phone_e164)
            ->first();
    }

    private function displayNameForPhone(?WhatsappWallet $wallet, string $phoneE164): string
    {
        if ($wallet !== null) {
            $name = $wallet->displayName();

            return $name !== null && trim($name) !== '' ? trim($name) : $phoneE164;
        }

        return $phoneE164;
    }

    private function notifyMembersInvited(WalletSaveTogetherPot $pot): void
    {
        $creator = $pot->creatorWallet;
        $creatorName = $creator ? $this->displayNameForPhone($creator, (string) $creator->phone_e164) : 'Someone';
        $shareLabel = WhatsappWalletMoneyFormatter::format((float) $pot->per_member_share, (string) $pot->currency);
        $instance = WhatsappEvolutionConfigResolver::walletInstance();

        foreach ($pot->members as $member) {
            if ($member->role === WalletSaveTogetherMember::ROLE_CREATOR) {
                continue;
            }

            $wallet = $member->wallet_id
                ? WhatsappWallet::query()->find($member->wallet_id)
                : WhatsappWallet::query()->where('phone_e164', $member->phone_e164)->where('status', WhatsappWallet::STATUS_ACTIVE)->first();

            if ($wallet instanceof WhatsappWallet) {
                $this->consumerPush->notifyGeneric($wallet, 'Save Together invite', sprintf(
                    '%s invited you to save together: %s. Your share: %s.',
                    $creatorName,
                    $pot->title,
                    $shareLabel,
                ), [
                    'type' => 'save_together_invite',
                    'save_together_pot_id' => (string) $pot->public_id,
                    'screen' => 'saving',
                ]);
            }

            if ($instance === '') {
                continue;
            }

            $lines = [
                '🤝 *Save Together*',
                '',
                sprintf('%s invited you to *%s*.', $creatorName, $pot->title),
                sprintf('Your share: *%s* (contribute any amount until complete).', $shareLabel),
                '',
                'Reply *ST CONTRIBUTE '.$pot->public_id.'* to add money or *ST DECLINE '.$pot->public_id.'* to decline.',
                'Or open CheckoutNow → Savings → Save Together.',
            ];
            $this->whatsappClient->sendText($instance, (string) $member->phone_e164, implode("\n", $lines));
        }
    }

    private function notifyPotUnlocked(WalletSaveTogetherPot $pot): void
    {
        $instance = WhatsappEvolutionConfigResolver::walletInstance();

        foreach ($pot->members as $member) {
            if ((float) $member->contributed_amount < 0.01) {
                continue;
            }

            $wallet = WhatsappWallet::query()->where('phone_e164', $member->phone_e164)->where('status', WhatsappWallet::STATUS_ACTIVE)->first();
            if ($wallet instanceof WhatsappWallet) {
                $this->consumerPush->notifyGeneric($wallet, 'Save Together unlocked', sprintf(
                    '%s is unlocked. Withdraw your contribution (₦%s) in the app.',
                    $pot->title,
                    number_format($member->refundableAmount(), 2),
                ), [
                    'type' => 'save_together_unlocked',
                    'save_together_pot_id' => (string) $pot->public_id,
                    'screen' => 'saving',
                ]);
            }

            if ($instance === '') {
                continue;
            }

            $this->whatsappClient->sendText(
                $instance,
                (string) $member->phone_e164,
                sprintf(
                    "🔓 *Save Together unlocked*\n\n*%s* is unlocked. Withdraw your saved amount in CheckoutNow or reply *ST WITHDRAW %s*.",
                    $pot->title,
                    $pot->public_id,
                ),
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function serializePot(WalletSaveTogetherPot $pot, ?WhatsappWallet $viewerWallet = null): array
    {
        $pot->loadMissing('members');
        $viewerMember = $viewerWallet ? $this->findMemberForWallet($pot, $viewerWallet) : null;
        $activeMembers = $pot->members->filter(fn (WalletSaveTogetherMember $m) => $m->countsTowardUnlock());
        $completedCount = $activeMembers->filter(fn (WalletSaveTogetherMember $m) => $m->hasCompletedShare())->count();

        return [
            'id' => (string) $pot->public_id,
            'title' => $pot->title,
            'target_amount' => (float) $pot->target_amount,
            'per_member_share' => (float) $pot->per_member_share,
            'total_contributed' => (float) $pot->total_contributed,
            'completion_mode' => $pot->completion_mode,
            'deadline_at' => $pot->deadline_at?->toIso8601String(),
            'status' => $pot->status,
            'unlocked_at' => $pot->unlocked_at?->toIso8601String(),
            'closed_at' => $pot->closed_at?->toIso8601String(),
            'currency' => (string) $pot->currency,
            'member_count' => $activeMembers->count(),
            'completed_member_count' => $completedCount,
            'note' => is_array($pot->meta) ? ($pot->meta['note'] ?? null) : null,
            'my_contributed' => $viewerMember ? (float) $viewerMember->contributed_amount : null,
            'my_withdrawn' => $viewerMember ? (float) $viewerMember->withdrawn_amount : null,
            'my_share_target' => $viewerMember ? (float) $viewerMember->share_target : null,
            'my_remaining_share' => $viewerMember ? $viewerMember->remainingShare() : null,
            'my_refundable' => $viewerMember ? $viewerMember->refundableAmount() : null,
            'my_status' => $viewerMember?->status,
            'members' => $pot->members->map(fn (WalletSaveTogetherMember $m) => [
                'phone_e164' => (string) $m->phone_e164,
                'display_name' => $m->display_name,
                'role' => $m->role,
                'share_target' => (float) $m->share_target,
                'contributed_amount' => (float) $m->contributed_amount,
                'withdrawn_amount' => (float) $m->withdrawn_amount,
                'status' => $m->status,
            ])->values()->all(),
        ];
    }
}
