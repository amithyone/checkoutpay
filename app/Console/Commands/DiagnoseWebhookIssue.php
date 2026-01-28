<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\BusinessWebsite;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DiagnoseWebhookIssue extends Command
{
    protected $signature = 'webhooks:diagnose {business_id? : Business ID to diagnose}';
    protected $description = 'Diagnose why fadded.net webhooks are not being sent';

    public function handle()
    {
        $businessId = $this->argument('business_id');
        
        // Find FADDED SOCIAL MEDIA CONCEPTS business
        if ($businessId) {
            $business = Business::find($businessId);
        } else {
            $business = Business::where('name', 'like', '%FADDED%')->first();
        }
        
        if (!$business) {
            $this->error('Business not found');
            return 1;
        }
        
        $this->info("Business: {$business->name} (ID: {$business->id})\n");
        
        // Get all websites for this business
        $websites = BusinessWebsite::where('business_id', $business->id)->get();
        
        $this->info("Websites under this business:");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        
        foreach ($websites as $website) {
            $this->line("ID: {$website->id}");
            $this->line("  URL: {$website->website_url}");
            $this->line("  Webhook URL: " . ($website->webhook_url ?? 'None'));
            $this->line("  Is Approved: " . ($website->is_approved ? 'Yes' : 'No'));
            $this->line("  Approved At: " . ($website->approved_at ? $website->approved_at->format('Y-m-d H:i:s') : 'Never'));
            
            // Check if webhook URL is empty or null
            if (empty($website->webhook_url)) {
                $this->warn("  ⚠️  NO WEBHOOK URL SET!");
            } elseif (!$website->is_approved) {
                $this->warn("  ⚠️  WEBSITE NOT APPROVED!");
            } else {
                $this->info("  ✅ Ready for webhooks");
            }
            $this->newLine();
        }
        
        // Get recent approved payments
        $payments = Payment::where('business_id', $business->id)
            ->where('status', Payment::STATUS_APPROVED)
            ->latest()
            ->limit(5)
            ->get();
        
        $this->info("Recent Approved Payments:");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        
        foreach ($payments as $payment) {
            $payment->load(['website', 'business.websites']);
            
            $this->line("Payment ID: {$payment->id}");
            $this->line("  Transaction ID: {$payment->transaction_id}");
            $this->line("  Amount: {$payment->amount}");
            $this->line("  Business Website ID: " . ($payment->business_website_id ?? 'None'));
            
            if ($payment->website) {
                $this->line("  Payment Website: {$payment->website->website_url}");
                $this->line("  Payment Website Webhook: " . ($payment->website->webhook_url ?? 'None'));
            }
            
            $this->line("  Webhook Status: " . ($payment->webhook_status ?? 'pending'));
            
            // Simulate webhook collection logic
            $this->line("\n  Simulating webhook collection:");
            $webhookUrls = [];
            
            // 1. Payment's website webhook
            if ($payment->website && $payment->website->webhook_url) {
                $webhookUrls[] = [
                    'url' => $payment->website->webhook_url,
                    'type' => 'website',
                    'website_id' => $payment->website->id,
                    'source' => 'payment_website',
                ];
                $this->line("    ✓ Added payment website webhook: {$payment->website->webhook_url}");
            }
            
            // 2. All business websites
            $businessWebsites = BusinessWebsite::where('business_id', $business->id)
                ->where('is_approved', true)
                ->whereNotNull('webhook_url')
                ->where('webhook_url', '!=', '')
                ->get();
            
            $this->line("    Found {$businessWebsites->count()} approved websites with webhooks:");
            
            foreach ($businessWebsites as $website) {
                $alreadyAdded = false;
                foreach ($webhookUrls as $existing) {
                    if (isset($existing['website_id']) && $existing['website_id'] === $website->id) {
                        $alreadyAdded = true;
                        break;
                    }
                    if ($existing['url'] === $website->webhook_url) {
                        $alreadyAdded = true;
                        break;
                    }
                }
                
                if (!$alreadyAdded) {
                    $webhookUrls[] = [
                        'url' => $website->webhook_url,
                        'type' => 'business_website',
                        'website_id' => $website->id,
                        'source' => 'business_websites',
                    ];
                    $this->info("    ✓ Added business website webhook: {$website->website_url} -> {$website->webhook_url}");
                } else {
                    $this->warn("    ⚠️  Skipped (already added): {$website->website_url}");
                }
            }
            
            // Check if fadded.net is included
            $faddedNetIncluded = false;
            foreach ($webhookUrls as $webhook) {
                if (isset($webhook['website_id'])) {
                    $w = BusinessWebsite::find($webhook['website_id']);
                    if ($w && stripos($w->website_url, 'fadded.net') !== false) {
                        $faddedNetIncluded = true;
                        $this->info("    ✅ fadded.net IS included in webhook list");
                        break;
                    }
                }
            }
            
            if (!$faddedNetIncluded) {
                $this->error("    ❌ fadded.net IS NOT included in webhook list!");
            }
            
            // Check webhook_urls_sent
            if ($payment->webhook_urls_sent && is_array($payment->webhook_urls_sent)) {
                $this->line("\n  Webhooks Actually Sent:");
                foreach ($payment->webhook_urls_sent as $sent) {
                    $statusIcon = $sent['status'] === 'success' ? '✅' : '❌';
                    $this->line("    {$statusIcon} {$sent['url']} ({$sent['type']})");
                }
            }
            
            $this->newLine();
        }
        
        return 0;
    }
}
