<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\Business;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackfillPaymentWebsites extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:backfill-websites {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill missing website_id for payments by matching webhook_url against approved websites';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        $this->info('Starting website backfill for payments...');
        
        // Get all payments without website_id but with business_id
        $payments = Payment::whereNull('business_website_id')
            ->whereNotNull('business_id')
            ->whereNotNull('webhook_url')
            ->with('business')
            ->get();
        
        $this->info("Found {$payments->count()} payments without website_id");
        
        $updated = 0;
        $skipped = 0;
        $errors = 0;
        
        foreach ($payments as $payment) {
            $business = $payment->business;
            if (!$business) {
                $skipped++;
                continue;
            }
            
            // Try to identify website from webhook_url
            $websiteId = $this->identifyWebsiteFromUrl($payment->webhook_url, $business);
            
            if ($websiteId) {
                if (!$dryRun) {
                    $payment->update(['business_website_id' => $websiteId]);
                }
                $updated++;
                $this->line("✓ Payment {$payment->transaction_id}: Matched to website ID {$websiteId}");
            } else {
                // Try single website fallback
                $approvedWebsites = $business->approvedWebsites;
                if ($approvedWebsites->count() === 1) {
                    $websiteId = $approvedWebsites->first()->id;
                    if (!$dryRun) {
                        $payment->update(['business_website_id' => $websiteId]);
                    }
                    $updated++;
                    $this->line("✓ Payment {$payment->transaction_id}: Assigned single website ID {$websiteId}");
                } else {
                    $skipped++;
                    $this->warn("✗ Payment {$payment->transaction_id}: Could not identify website");
                }
            }
        }
        
        $this->info("\nSummary:");
        $this->info("  Updated: {$updated}");
        $this->info("  Skipped: {$skipped}");
        $this->info("  Errors: {$errors}");
        
        if ($dryRun) {
            $this->warn("\nThis was a dry run. Use without --dry-run to apply changes.");
        }
        
        return 0;
    }
    
    /**
     * Identify website from URL by matching against approved websites
     */
    protected function identifyWebsiteFromUrl(string $url, Business $business): ?int
    {
        if (empty($url)) {
            return null;
        }

        $parsedUrl = parse_url($url);
        $urlHost = $parsedUrl['host'] ?? null;
        
        if (!$urlHost) {
            // If no host, try to extract from the URL itself
            $urlHost = preg_replace('#^https?://#', '', $url);
            $urlHost = preg_replace('#/.*$#', '', $urlHost);
        }

        if (!$urlHost) {
            return null;
        }

        // Normalize host: remove www. prefix and convert to lowercase
        $urlHost = strtolower(preg_replace('/^www\./', '', $urlHost));

        // Check against all approved websites
        $approvedWebsites = $business->approvedWebsites;
        
        foreach ($approvedWebsites as $website) {
            $websiteUrl = $website->website_url;
            $websiteHost = parse_url($websiteUrl, PHP_URL_HOST);
            
            if (!$websiteHost) {
                // If no host in website_url, try to extract it
                $websiteHost = preg_replace('#^https?://#', '', $websiteUrl);
                $websiteHost = preg_replace('#/.*$#', '', $websiteHost);
            }
            
            if ($websiteHost) {
                // Normalize website host
                $websiteHost = strtolower(preg_replace('/^www\./', '', $websiteHost));
                
                // Exact match
                if ($urlHost === $websiteHost) {
                    return $website->id;
                }
                
                // Subdomain match (e.g., api.example.com matches example.com)
                $urlParts = explode('.', $urlHost);
                $websiteParts = explode('.', $websiteHost);
                
                // Check if URL host ends with website host (for subdomain matching)
                if (count($urlParts) >= count($websiteParts)) {
                    $urlSuffix = implode('.', array_slice($urlParts, -count($websiteParts)));
                    if ($urlSuffix === $websiteHost) {
                        return $website->id;
                    }
                }
            }
        }

        return null;
    }
}
