<?php

namespace App\Listeners;

use App\Events\PaymentApproved;
use App\Models\Membership;
use App\Models\MembershipSubscription;
use App\Services\MembershipCardPdfService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CreateMembershipSubscriptionOnPaymentApproved
{
    /**
     * Create the event listener.
     */
    public function __construct(
        protected MembershipCardPdfService $cardPdfService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(PaymentApproved $event): void
    {
        $payment = $event->payment;

        // Check if this payment is linked to a membership
        $emailData = $payment->email_data ?? [];
        $membershipId = $emailData['membership_id'] ?? null;

        if (!$membershipId) {
            return; // Not a membership payment
        }

        $membership = Membership::find($membershipId);
        if (!$membership) {
            Log::warning('Membership not found for payment', [
                'payment_id' => $payment->id,
                'membership_id' => $membershipId,
            ]);
            return;
        }

        // Check if subscription already exists
        $existingSubscription = MembershipSubscription::where('payment_id', $payment->id)->first();
        if ($existingSubscription) {
            return; // Already processed
        }

        try {
            // Get member details from email_data
            $memberName = $emailData['member_name'] ?? $payment->payer_name;
            $memberEmail = $emailData['member_email'] ?? null;
            $memberPhone = $emailData['member_phone'] ?? null;

            // Calculate expiry date based on membership duration
            $startDate = Carbon::now();
            $expiresAt = $startDate->copy();

            switch ($membership->duration_type) {
                case 'days':
                    $expiresAt->addDays($membership->duration_value);
                    break;
                case 'weeks':
                    $expiresAt->addWeeks($membership->duration_value);
                    break;
                case 'months':
                    $expiresAt->addMonths($membership->duration_value);
                    break;
                case 'years':
                    $expiresAt->addYears($membership->duration_value);
                    break;
            }

            // Create subscription
            $subscription = MembershipSubscription::create([
                'membership_id' => $membership->id,
                'payment_id' => $payment->id,
                'member_name' => $memberName,
                'member_email' => $memberEmail,
                'member_phone' => $memberPhone,
                'start_date' => $startDate,
                'expires_at' => $expiresAt,
                'status' => 'active',
            ]);

            // Increment membership current_members count
            $membership->increment('current_members');

            // Generate membership card PDF
            $this->cardPdfService->generatePdf($subscription);

            Log::info('Membership subscription created on payment approval', [
                'subscription_id' => $subscription->id,
                'subscription_number' => $subscription->subscription_number,
                'membership_id' => $membership->id,
                'payment_id' => $payment->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create membership subscription on payment approval', [
                'membership_id' => $membershipId,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
