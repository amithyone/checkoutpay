<?php

namespace App\Console\Commands;

use App\Models\MembershipSubscription;
use App\Mail\MembershipExpired;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CheckExpiredMemberships extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'memberships:check-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for expired memberships and send expiration notifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for expired memberships...');

        // Find memberships that expired today or yesterday (to catch any missed ones)
        $expiredSubscriptions = MembershipSubscription::where('status', 'active')
            ->whereDate('expires_at', '<=', Carbon::today())
            ->whereNull('expiration_email_sent_at') // Only send once
            ->with(['membership.business', 'membership.category'])
            ->get();

        if ($expiredSubscriptions->isEmpty()) {
            $this->info('No expired memberships found.');
            return 0;
        }

        $this->info("Found {$expiredSubscriptions->count()} expired membership(s).");

        $sentCount = 0;
        $failedCount = 0;

        foreach ($expiredSubscriptions as $subscription) {
            // Update status to expired
            $subscription->status = 'expired';
            $subscription->save();

            // Decrement membership current_members count
            if ($subscription->membership) {
                $subscription->membership->decrement('current_members');
            }

            // Send expiration email if member has email
            if ($subscription->member_email) {
                try {
                    Mail::to($subscription->member_email)->send(new MembershipExpired($subscription));
                    
                    // Mark email as sent
                    $subscription->expiration_email_sent_at = Carbon::now();
                    $subscription->save();

                    $sentCount++;
                    $this->info("Expiration email sent to: {$subscription->member_email} (Subscription: {$subscription->subscription_number})");
                } catch (\Exception $e) {
                    $failedCount++;
                    Log::error('Failed to send membership expiration email', [
                        'subscription_id' => $subscription->id,
                        'member_email' => $subscription->member_email,
                        'error' => $e->getMessage(),
                    ]);
                    $this->error("Failed to send email to: {$subscription->member_email} - {$e->getMessage()}");
                }
            } else {
                $this->warn("No email address for subscription: {$subscription->subscription_number}");
            }
        }

        $this->info("Processed {$expiredSubscriptions->count()} expired membership(s).");
        $this->info("Emails sent: {$sentCount}, Failed: {$failedCount}");

        return 0;
    }
}
