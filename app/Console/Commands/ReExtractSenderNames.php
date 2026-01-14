<?php

namespace App\Console\Commands;

use App\Models\ProcessedEmail;
use App\Services\PaymentMatchingService;
use App\Services\EmailExtractionService;
use App\Services\DescriptionFieldExtractor;
use App\Services\SenderNameExtractor;
use App\Services\TransactionLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReExtractSenderNames extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:re-extract-sender-names {--analyze-only : Only analyze without clearing/re-extracting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear and re-extract sender names from all processed emails, then analyze success rates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $matchingService = new PaymentMatchingService(new TransactionLogService());
        $emailExtractionService = new EmailExtractionService();
        $descExtractor = new DescriptionFieldExtractor();
        $nameExtractor = new SenderNameExtractor();

        if ($this->option('analyze-only')) {
            $this->info('Analyzing current sender name extraction success rate...');
            $this->analyzeSuccessRate();
            return 0;
        }

        $this->info('🔄 Starting sender name re-extraction process...');
        $this->newLine();

        // Step 1: Clear all sender names
        $this->info('Step 1: Clearing all existing sender names...');
        $totalEmails = ProcessedEmail::count();
        $cleared = ProcessedEmail::whereNotNull('sender_name')->update(['sender_name' => null]);
        $this->info("   ✅ Cleared sender names from {$cleared} emails");
        $this->newLine();

        // Step 2: Re-extract sender names
        $this->info('Step 2: Re-extracting sender names...');
        $emails = ProcessedEmail::all();
        $this->info("   Processing {$emails->count()} emails...");
        
        $bar = $this->output->createProgressBar($emails->count());
        $bar->start();

        $stats = [
            'total' => 0,
            'extracted' => 0,
            'failed' => 0,
            'has_text_body' => 0,
            'has_html_body' => 0,
            'has_both' => 0,
            'has_neither' => 0,
            'extraction_methods' => [],
            'failed_reasons' => [],
        ];

        foreach ($emails as $email) {
            $stats['total']++;
            
            // Track body availability
            $hasText = !empty(trim($email->text_body ?? ''));
            $hasHtml = !empty(trim($email->html_body ?? ''));
            
            if ($hasText && $hasHtml) {
                $stats['has_both']++;
            } elseif ($hasText) {
                $stats['has_text_body']++;
            } elseif ($hasHtml) {
                $stats['has_html_body']++;
            } else {
                $stats['has_neither']++;
            }

            try {
                // Decode quoted-printable encoding from text_body and html_body
                $textBody = $email->text_body ?? '';
                $htmlBody = $email->html_body ?? '';
                
                // Decode quoted-printable (=20, =3D, etc.)
                $textBody = preg_replace('/=20/', ' ', $textBody);
                $textBody = preg_replace('/=3D/', '=', $textBody);
                $textBody = html_entity_decode($textBody, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                
                $htmlBody = preg_replace('/=20/', ' ', $htmlBody);
                $htmlBody = preg_replace('/=3D/', '=', $htmlBody);
                $htmlBody = html_entity_decode($htmlBody, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                
                // Re-extract payment info
                $emailData = [
                    'subject' => $email->subject,
                    'from' => $email->from_email,
                    'text' => $textBody,
                    'html' => $htmlBody,
                    'date' => $email->email_date ? $email->email_date->toDateTimeString() : null,
                ];

                // Try PaymentMatchingService first (uses Python)
                $extractionResult = $matchingService->extractPaymentInfo($emailData);
                
                // If that fails, try EmailExtractionService directly (PHP-based)
                if (!$extractionResult || !isset($extractionResult['data'])) {
                    $hasText = !empty(trim($textBody));
                    $hasHtml = !empty(trim($htmlBody));
                    
                    if ($hasText) {
                        // Convert HTML to plain text if needed
                        $plainText = strip_tags($textBody);
                        $plainText = preg_replace('/\s+/', ' ', $plainText);
                        
                        $extractedInfo = $emailExtractionService->extractFromTextBody(
                            $plainText,
                            $email->subject ?? '',
                            $email->from_email ?? '',
                            $email->email_date ? $email->email_date->toDateTimeString() : null
                        );
                        if ($extractedInfo) {
                            $extractionResult = [
                                'data' => $extractedInfo,
                                'method' => 'text_body_fallback'
                            ];
                        }
                    }
                    
                    if (!$extractionResult && $hasHtml) {
                        $extractedInfo = $emailExtractionService->extractFromHtmlBody(
                            $htmlBody,
                            $email->subject ?? '',
                            $email->from_email ?? '',
                            $descExtractor,
                            $nameExtractor
                        );
                        if ($extractedInfo) {
                            $extractionResult = [
                                'data' => $extractedInfo,
                                'method' => 'html_body_fallback'
                            ];
                        }
                    }
                }

                if ($extractionResult && isset($extractionResult['data'])) {
                    $extractedInfo = $extractionResult['data'];
                    $method = $extractionResult['method'] ?? 'unknown';
                    
                    // Track extraction method
                    if (!isset($stats['extraction_methods'][$method])) {
                        $stats['extraction_methods'][$method] = 0;
                    }
                    $stats['extraction_methods'][$method]++;

                    // Update sender name if extracted
                    if (!empty($extractedInfo['sender_name'])) {
                        $email->update([
                            'sender_name' => $extractedInfo['sender_name'],
                            'extracted_data' => $extractedInfo,
                        ]);
                        $stats['extracted']++;
                    } else {
                        $stats['failed']++;
                        $reason = 'No sender name in extraction result';
                        if (!isset($stats['failed_reasons'][$reason])) {
                            $stats['failed_reasons'][$reason] = 0;
                        }
                        $stats['failed_reasons'][$reason]++;
                    }
                } else {
                    $stats['failed']++;
                    $reason = 'Extraction returned no data';
                    if (!isset($stats['failed_reasons'][$reason])) {
                        $stats['failed_reasons'][$reason] = 0;
                    }
                    $stats['failed_reasons'][$reason]++;
                }
            } catch (\Exception $e) {
                $stats['failed']++;
                $reason = 'Exception: ' . substr($e->getMessage(), 0, 50);
                if (!isset($stats['failed_reasons'][$reason])) {
                    $stats['failed_reasons'][$reason] = 0;
                }
                $stats['failed_reasons'][$reason]++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Step 3: Analyze success rate
        $this->info('Step 3: Analyzing results...');
        $this->newLine();
        $this->displayResults($stats);
        
        // Step 4: Show improvement suggestions
        $this->newLine();
        $this->showImprovementSuggestions($stats);

        return 0;
    }

    /**
     * Display analysis results
     */
    protected function displayResults(array $stats)
    {
        $successRate = $stats['total'] > 0 
            ? round(($stats['extracted'] / $stats['total']) * 100, 2) 
            : 0;

        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('📊 SENDER NAME EXTRACTION RESULTS');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->newLine();

        $this->info("Total Emails Processed: {$stats['total']}");
        $this->info("✅ Successfully Extracted: {$stats['extracted']}");
        $this->info("❌ Failed to Extract: {$stats['failed']}");
        $this->info("📈 Success Rate: {$successRate}%");
        $this->newLine();

        $this->info('Email Body Availability:');
        $this->info("   📄 Text Body Only: {$stats['has_text_body']}");
        $this->info("   🌐 HTML Body Only: {$stats['has_html_body']}");
        $this->info("   📄🌐 Both Text & HTML: {$stats['has_both']}");
        $this->info("   ⚠️  Neither Available: {$stats['has_neither']}");
        $this->newLine();

        if (!empty($stats['extraction_methods'])) {
            $this->info('Extraction Methods Used:');
            foreach ($stats['extraction_methods'] as $method => $count) {
                $percentage = round(($count / $stats['total']) * 100, 2);
                $this->info("   • {$method}: {$count} ({$percentage}%)");
            }
            $this->newLine();
        }

        if (!empty($stats['failed_reasons'])) {
            $this->warn('Top Failure Reasons:');
            arsort($stats['failed_reasons']);
            $topReasons = array_slice($stats['failed_reasons'], 0, 5, true);
            foreach ($topReasons as $reason => $count) {
                $percentage = round(($count / $stats['failed']) * 100, 2);
                $this->warn("   • {$reason}: {$count} ({$percentage}% of failures)");
            }
        }
    }

    /**
     * Show improvement suggestions
     */
    protected function showImprovementSuggestions(array $stats)
    {
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('💡 IMPROVEMENT SUGGESTIONS');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->newLine();

        $successRate = $stats['total'] > 0 
            ? round(($stats['extracted'] / $stats['total']) * 100, 2) 
            : 0;

        if ($successRate < 50) {
            $this->error("⚠️  Low success rate ({$successRate}%). Critical improvements needed!");
            $this->newLine();
        } elseif ($successRate < 70) {
            $this->warn("⚠️  Moderate success rate ({$successRate}%). Some improvements recommended.");
            $this->newLine();
        } else {
            $this->info("✅ Good success rate ({$successRate}%). Minor optimizations possible.");
            $this->newLine();
        }

        // Suggestions based on data
        if ($stats['has_neither'] > 0) {
            $percentage = round(($stats['has_neither'] / $stats['total']) * 100, 2);
            $this->warn("1. {$stats['has_neither']} emails ({$percentage}%) have no text or HTML body.");
            $this->info("   → Check email fetching/parsing to ensure body content is captured.");
            $this->newLine();
        }

        if ($stats['has_html_body'] > $stats['has_text_body']) {
            $this->info("2. More emails have HTML body ({$stats['has_html_body']}) than text body ({$stats['has_text_body']}).");
            $this->info("   → Ensure HTML parsing is robust and handles various email formats.");
            $this->newLine();
        }

        if (isset($stats['extraction_methods']['text_body']) && isset($stats['extraction_methods']['html_body'])) {
            $textSuccess = $stats['extraction_methods']['text_body'] ?? 0;
            $htmlSuccess = $stats['extraction_methods']['html_body'] ?? 0;
            
            if ($textSuccess > $htmlSuccess) {
                $this->info("3. Text body extraction ({$textSuccess}) performs better than HTML ({$htmlSuccess}).");
                $this->info("   → Consider prioritizing text body extraction when available.");
            } else {
                $this->info("3. HTML body extraction ({$htmlSuccess}) performs better than text ({$textSuccess}).");
                $this->info("   → Consider improving HTML parsing or prioritizing HTML when available.");
            }
            $this->newLine();
        }

        // Check for common patterns in failed extractions
        $this->info("4. Review failed extractions:");
        $this->info("   → Check if sender names are in email subject lines");
        $this->info("   → Verify description field parsing (43-digit format)");
        $this->info("   → Check for bank-specific formats (GTBank, Access Bank, etc.)");
        $this->newLine();

        $this->info("5. Next Steps:");
        $this->info("   → Sample failed emails: SELECT * FROM processed_emails WHERE sender_name IS NULL LIMIT 10");
        $this->info("   → Check extraction patterns in EmailExtractionService.php");
        $this->info("   → Review SenderNameExtractor.php for pattern improvements");
        $this->info("   → Test with real email samples to identify missing patterns");
    }

    /**
     * Analyze current success rate without re-extracting
     */
    protected function analyzeSuccessRate()
    {
        $total = ProcessedEmail::count();
        $withSenderName = ProcessedEmail::whereNotNull('sender_name')->count();
        $withoutSenderName = ProcessedEmail::whereNull('sender_name')->count();
        
        $successRate = $total > 0 ? round(($withSenderName / $total) * 100, 2) : 0;

        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('📊 CURRENT SENDER NAME EXTRACTION STATUS');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->newLine();

        $this->info("Total Emails: {$total}");
        $this->info("✅ With Sender Name: {$withSenderName}");
        $this->info("❌ Without Sender Name: {$withoutSenderName}");
        $this->info("📈 Current Success Rate: {$successRate}%");
        $this->newLine();

        // Show breakdown by extraction method
        $byMethod = ProcessedEmail::select('extraction_method', DB::raw('count(*) as count'))
            ->whereNotNull('sender_name')
            ->groupBy('extraction_method')
            ->get();

        if ($byMethod->count() > 0) {
            $this->info('Success by Extraction Method:');
            foreach ($byMethod as $method) {
                $percentage = round(($method->count / $withSenderName) * 100, 2);
                $this->info("   • {$method->extraction_method}: {$method->count} ({$percentage}%)");
            }
            $this->newLine();
        }

        // Show emails with amount but no sender name
        $withAmountNoName = ProcessedEmail::whereNotNull('amount')
            ->whereNull('sender_name')
            ->count();
        
        if ($withAmountNoName > 0) {
            $this->warn("⚠️  {$withAmountNoName} emails have amount extracted but no sender name.");
            $this->info("   → These are good candidates for pattern improvement.");
            $this->newLine();
        }
    }
}
