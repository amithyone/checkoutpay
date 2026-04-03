<?php

namespace App\Listeners;

use App\Events\PaymentApproved;
use App\Models\Membership;
use App\Models\MembershipSubscription;
use App\Models\NigtaxProPendingRegistration;
use App\Services\NigtaxProSubscriptionService;
use App\Services\MembershipCardPdfService;
use App\Mail\MembershipActivated;
use App\Mail\MembershipPaymentReceiptMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

        // NigTax PRO modal: email_data could lack membership_id if approved before Payment::approve() fix
        if (! $membershipId) {
            $pending = NigtaxProPendingRegistration::query()->where('payment_id', $payment->id)->first();
            if ($pending) {
                $proMembership = app(NigtaxProSubscriptionService::class)->findMembership();
                if ($proMembership) {
                    $membershipId = $proMembership->id;
                    $emailData['membership_id'] = $membershipId;
                    $emailData['member_name'] = $emailData['member_name'] ?? $pending->member_name;
                    $emailData['member_email'] = $emailData['member_email'] ?? $pending->email;
                }
            }
        }

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
            
            // Refresh subscription to get updated card_pdf_path
            $subscription->refresh();
            $subscription->load('membership');

            // Send activation email to member
            if ($subscription->member_email) {
                try {
                    Mail::to($subscription->member_email)->send(new MembershipPaymentReceiptMail($subscription, $payment));
                } catch (\Exception $e) {
                    Log::error('Failed to send membership payment receipt email', [
                        'subscription_id' => $subscription->id,
                        'member_email' => $subscription->member_email,
                        'error' => $e->getMessage(),
                    ]);
                }
                try {
                    Mail::to($subscription->member_email)->send(new MembershipActivated($subscription));
                    Log::info('Membership activation email sent', [
                        'subscription_id' => $subscription->id,
                        'member_email' => $subscription->member_email,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to send membership activation email', [
                        'subscription_id' => $subscription->id,
                        'member_email' => $subscription->member_email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

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
