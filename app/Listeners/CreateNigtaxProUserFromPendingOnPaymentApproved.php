<?php

namespace App\Listeners;

use App\Events\PaymentApproved;
use App\Models\Membership;
use App\Models\NigtaxProPendingRegistration;
use App\Models\NigtaxProUser;
use App\Services\NigtaxProSubscriptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * When a NigTax PRO membership payment is approved and the payer started checkout from NigTax (pending row exists),
 * create their nigtax_pro_users row with the password they chose in the modal.
 */
class CreateNigtaxProUserFromPendingOnPaymentApproved
{
    public function __construct(
        protected NigtaxProSubscriptionService $proSubscription
    ) {}

    public function handle(PaymentApproved $event): void
    {
        $payment = $event->payment;

        $pending = NigtaxProPendingRegistration::query()->where('payment_id', $payment->id)->first();

        $membershipId = $payment->email_data['membership_id'] ?? null;
        // Older approvals wiped email_data; pending row still ties this payment to NigTax PRO.
        if (! $membershipId && $pending) {
            $membershipId = $this->proSubscription->findMembership()?->id;
        }

        if (! $membershipId) {
            return;
        }

        $membership = Membership::query()->find($membershipId);
        if (! $membership || $membership->slug !== $this->proSubscription->membershipSlug()) {
            return;
        }

        if (! $pending) {
            return;
        }

        $email = $this->proSubscription->normalizeEmail($pending->email);

        try {
            if (NigtaxProUser::query()->where('email', $email)->exists()) {
                $pending->delete();

                return;
            }

            DB::table('nigtax_pro_users')->insert([
                'email' => $email,
                'password' => $pending->password_hash,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $pending->delete();

            Log::info('NigTax PRO user created from pending registration', [
                'payment_id' => $payment->id,
                'email' => $email,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to create NigTax PRO user from pending registration', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
