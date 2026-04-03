<?php

namespace App\Console\Commands;

use App\Events\PaymentApproved;
use App\Listeners\CreateMembershipSubscriptionOnPaymentApproved;
use App\Listeners\CreateNigtaxProUserFromPendingOnPaymentApproved;
use App\Models\MembershipSubscription;
use App\Models\NigtaxProPendingRegistration;
use App\Models\NigtaxProUser;
use App\Models\Payment;
use Illuminate\Console\Command;

/**
 * For payments approved while Payment::approve() stripped membership_id from email_data:
 * pending rows may remain with no subscription or nigtax_pro_users row. Re-runs only the two
 * membership/PRO listeners (does not re-credit business or re-send payment webhooks).
 */
class RepairNigtaxProPendingCommand extends Command
{
    protected $signature = 'nigtax:repair-pro-approved-pending {--dry-run : Show what would run}';

    protected $description = 'Repair NigTax PRO subscriptions and login users from pending registrations (approved payments only)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $count = 0;

        foreach (NigtaxProPendingRegistration::query()->with('payment')->cursor() as $pending) {
            $payment = $pending->payment;
            if (! $payment instanceof Payment || $payment->status !== Payment::STATUS_APPROVED) {
                continue;
            }

            $hasSub = MembershipSubscription::query()->where('payment_id', $payment->id)->exists();
            $hasUser = NigtaxProUser::query()->where('email', $pending->email)->exists();

            if ($hasSub && $hasUser) {
                if (! $dry) {
                    $pending->delete();
                }

                continue;
            }

            $this->line("Payment {$payment->id} ({$payment->transaction_id}): subscription=".($hasSub ? 'yes' : 'no').' user='.($hasUser ? 'yes' : 'no'));

            if ($dry) {
                $count++;

                continue;
            }

            $payment->refresh();

            if (! $hasSub) {
                app(CreateMembershipSubscriptionOnPaymentApproved::class)->handle(new PaymentApproved($payment));
                $payment->refresh();
            }

            if (! NigtaxProUser::query()->where('email', $pending->email)->exists()) {
                app(CreateNigtaxProUserFromPendingOnPaymentApproved::class)->handle(new PaymentApproved($payment));
            }

            $pendingStillThere = NigtaxProPendingRegistration::query()->whereKey($pending->id)->exists();
            if (! $pendingStillThere) {
                $this->info("  Repaired payment {$payment->id}.");
            } else {
                $this->warn("  Pending row still exists for payment {$payment->id}; check logs.");
            }
            $count++;
        }

        if ($dry) {
            $this->info("Dry run: {$count} payment(s) would be processed.");
        } else {
            $this->info("Done. Processed {$count} repair run(s).");
        }

        return self::SUCCESS;
    }
}
