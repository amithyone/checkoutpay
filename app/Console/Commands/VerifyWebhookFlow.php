<?php

namespace App\Console\Commands;

use App\Jobs\SendWebhookNotification;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class VerifyWebhookFlow extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'webhooks:verify-flow {payment_id? : Specific payment ID to verify}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify that webhooks are sent to corresponding website when payment is approved';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $paymentId = $this->argument('payment_id');
        
        if ($paymentId) {
            $payment = Payment::find($paymentId);
            if (!$payment) {
                $this->error("Payment {$paymentId} not found");
                return 1;
            }
            $payments = collect([$payment]);
        } else {
            // Get recent approved payments with websites
            $payments = Payment::where('status', Payment::STATUS_APPROVED)
                ->whereNotNull('business_website_id')
                ->latest()
                ->limit(10)
                ->get();
        }

        if ($payments->isEmpty()) {
            $this->info('No approved payments with websites found');
            return 0;
        }

        $this->info("Verifying webhook flow for {$payments->count()} payment(s)\n");

        $verified = 0;
        $issues = 0;

        foreach ($payments as $payment) {
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("Payment: {$payment->transaction_id} (ID: {$payment->id})");
            
            // Load relationships
            $payment->load(['business.websites', 'website']);
            
            // Check payment website
            if ($payment->business_website_id) {
                $this->line("  Business Website ID: {$payment->business_website_id}");
                if ($payment->website) {
                    $this->line("  Website URL: {$payment->website->website_url}");
                    $this->line("  Website Webhook: " . ($payment->website->webhook_url ?? 'None'));
                    
                    if (!$payment->website->webhook_url) {
                        $this->warn("  ⚠️  Website has no webhook URL configured");
                        $issues++;
                    }
                } else {
                    $this->warn("  ⚠️  Website relationship not found");
                    $issues++;
                }
            } else {
                $this->line("  No business_website_id set");
            }
            
            // Check webhook status
            $this->line("  Webhook Status: " . ($payment->webhook_status ?? 'N/A'));
            $this->line("  Webhook Sent At: " . ($payment->webhook_sent_at ? $payment->webhook_sent_at->format('Y-m-d H:i:s') : 'Never'));
            
            // Check if webhook was sent to corresponding website
            if ($payment->webhook_urls_sent && is_array($payment->webhook_urls_sent)) {
                $correspondingWebsiteWebhookFound = false;
                $this->line("  Webhooks Sent:");
                foreach ($payment->webhook_urls_sent as $sent) {
                    $statusIcon = $sent['status'] === 'success' ? '✅' : '❌';
                    $this->line("    {$statusIcon} {$sent['url']} ({$sent['type']})");
                    
                    if ($payment->business_website_id && isset($sent['website_id']) && $sent['website_id'] === $payment->business_website_id) {
                        $correspondingWebsiteWebhookFound = true;
                        if ($sent['status'] === 'success') {
                            $this->info("    ✓ Corresponding website webhook sent successfully");
                        } else {
                            $this->warn("    ⚠️  Corresponding website webhook failed");
                            $issues++;
                        }
                    }
                }
                
                if ($payment->business_website_id && !$correspondingWebsiteWebhookFound) {
                    $this->error("    ✗ Corresponding website webhook NOT found in sent list!");
                    $issues++;
                } else if ($payment->business_website_id) {
                    $verified++;
                }
            } else {
                if ($payment->webhook_status === 'pending') {
                    $this->warn("  ⚠️  Webhook status is pending - webhook may not have been sent yet");
                    $issues++;
                } else {
                    $this->line("  No webhook URLs sent recorded");
                }
            }
            
            $this->newLine();
        }

        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->newLine();
        
        if ($verified > 0) {
            $this->info("✅ Verified: {$verified} payment(s) have corresponding website webhooks sent");
        }
        
        if ($issues > 0) {
            $this->warn("⚠️  Issues found: {$issues} issue(s) detected");
            $this->line("   Check logs for details or run: php artisan webhooks:resend-fadded-net");
        } else {
            $this->info("✅ No issues found - all webhooks are working correctly");
        }

        return 0;
    }
}
