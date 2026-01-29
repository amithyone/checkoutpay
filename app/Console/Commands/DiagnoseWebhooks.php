<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\BusinessWebsite;
use Illuminate\Console\Command;

class DiagnoseWebhooks extends Command
{
    protected $signature = 'webhooks:diagnose {--limit=20 : Number of payments to check}';
    protected $description = 'Diagnose webhook issues - check payments and their webhook configuration';

    public function handle()
    {
        $limit = (int) $this->option('limit');
        
        $this->info("=== Webhook Diagnostic Report ===\n");
        
        // 1. Webhook status distribution
        $this->info("1. Webhook Status Distribution (approved payments):");
        $stats = Payment::where('status', Payment::STATUS_APPROVED)
            ->selectRaw('COALESCE(webhook_status, "null") as status, count(*) as cnt')
            ->groupBy('webhook_status')
            ->get();
        
        foreach ($stats as $s) {
            $this->line("   {$s->status}: {$s->cnt}");
        }
        
        // 2. Approved payments without business_website_id
        $noWebsite = Payment::where('status', Payment::STATUS_APPROVED)
            ->whereNull('business_website_id')
            ->count();
        $withWebsite = Payment::where('status', Payment::STATUS_APPROVED)
            ->whereNotNull('business_website_id')
            ->count();
        
        $this->newLine();
        $this->info("2. Payment-Website Association:");
        $this->line("   With business_website_id: {$withWebsite}");
        $this->line("   Without business_website_id: {$noWebsite}");
        
        if ($noWebsite > 0) {
            $this->warn("   ⚠️  Payments without business_website_id may not have webhook URL!");
        }
        
        // 3. Websites without webhook_url
        $websitesNoWebhook = BusinessWebsite::where('is_approved', true)
            ->where(function ($q) {
                $q->whereNull('webhook_url')->orWhere('webhook_url', '');
            })
            ->count();
        
        $this->newLine();
        $this->info("3. Approved Websites:");
        $this->line("   Without webhook URL: {$websitesNoWebhook}");
        
        // 4. Sample of recent payments with webhook issues
        $this->newLine();
        $this->info("4. Recent Payments (last {$limit}):");
        
        $payments = Payment::where('status', Payment::STATUS_APPROVED)
            ->latest()
            ->limit($limit)
            ->get();
        
        $headers = ['ID', 'Transaction', 'WebID', 'Status', 'Attempts', 'Has Webhook URL?'];
        $rows = [];
        
        foreach ($payments as $p) {
            $p->load(['website']);
            $hasWebhook = 'No';
            if ($p->website && $p->website->webhook_url) {
                $hasWebhook = 'Yes';
            } elseif ($p->webhook_url) {
                $hasWebhook = 'Payment';
            } elseif ($p->business && $p->business->webhook_url) {
                $hasWebhook = 'Business';
            }
            
            $rows[] = [
                $p->id,
                substr($p->transaction_id, 0, 20),
                $p->business_website_id ?? '-',
                $p->webhook_status ?? 'null',
                $p->webhook_attempts ?? 0,
                $hasWebhook,
            ];
        }
        
        $this->table($headers, $rows);
        
        // 5. Queue status
        $this->newLine();
        $this->info("5. Queue Status:");
        try {
            $jobCount = \DB::table('jobs')->count();
            $failedCount = \DB::table('failed_jobs')->count();
            $this->line("   Pending jobs: {$jobCount}");
            $this->line("   Failed jobs: {$failedCount}");
            
            if ($jobCount > 0) {
                $this->warn("   ⚠️  Jobs in queue - ensure queue worker is running OR use cron endpoint (processes sync)");
            }
        } catch (\Exception $e) {
            $this->warn("   Could not check queue: {$e->getMessage()}");
        }
        
        $this->newLine();
        $this->info("=== Recommendations ===");
        $this->line("• Add cron URL to external service: " . url('/api/v1/cron/process-webhooks'));
        $this->line("• Cron runs webhooks SYNCHRONOUSLY (no queue worker needed)");
        $this->line("• Frequency: Every 1-5 minutes");
        $this->newLine();
        
        return 0;
    }
}
