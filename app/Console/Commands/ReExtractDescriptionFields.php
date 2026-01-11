<?php

namespace App\Console\Commands;

use App\Models\ProcessedEmail;
use App\Services\PaymentMatchingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReExtractDescriptionFields extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:re-extract-description-fields 
                            {--limit=100 : Maximum number of emails to process}
                            {--force : Process all emails, even if description_field already exists}
                            {--debug : Show detailed output for each email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-extract description_field (43-digit GTBank value) from existing processed emails';

    protected PaymentMatchingService $matchingService;

    public function __construct(PaymentMatchingService $matchingService)
    {
        parent::__construct();
        $this->matchingService = $matchingService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = (int) $this->option('limit');
        $force = $this->option('force');

        $this->info('🔄 Re-extracting description fields from processed emails...');
        $this->newLine();

        // Build query
        $query = ProcessedEmail::query();
        
        if (!$force) {
            // Only process emails where description_field is NULL
            $query->whereNull('description_field');
        }

        $totalEmails = $query->count();
        $emailsToProcess = $query->limit($limit)->get();

        if ($emailsToProcess->isEmpty()) {
            $this->info('✅ No emails found to process.');
            if (!$force) {
                $this->info('   (Use --force to process all emails, even if description_field exists)');
            }
            return 0;
        }

        $this->info("Found {$totalEmails} email(s) to process (processing {$emailsToProcess->count()})");
        $this->newLine();

        $bar = $this->output->createProgressBar($emailsToProcess->count());
        $bar->start();

        $successCount = 0;
        $failedCount = 0;
        $skippedCount = 0;
        $debug = $this->option('debug');

        foreach ($emailsToProcess as $email) {
            if ($debug) {
                $this->newLine();
                $this->line("Processing Email ID: {$email->id} | Subject: " . substr($email->subject ?? 'No Subject', 0, 50));
            }
            try {
                // Skip if description_field already exists and not forcing
                if (!$force && $email->description_field) {
                    $skippedCount++;
                    $bar->advance();
                    continue;
                }

                // Prepare email data for extraction
                $emailData = [
                    'subject' => $email->subject ?? '',
                    'from' => $email->from_email ?? '',
                    'text' => $email->text_body ?? '',
                    'html' => $email->html_body ?? '',
                    'date' => $email->email_date ? $email->email_date->toDateTimeString() : null,
                ];

                // Extract payment info
                $extractionResult = $this->matchingService->extractPaymentInfo($emailData);

                if (!$extractionResult || !is_array($extractionResult)) {
                    // Try to extract description field directly from text/html as fallback
                    $descriptionField = $this->extractDescriptionFieldDirectly($email->text_body ?? '', $email->html_body ?? '');
                    if ($descriptionField && strlen($descriptionField) === 43) {
                        $email->update(['description_field' => $descriptionField]);
                        $successCount++;
                    } else {
                        $failedCount++;
                    }
                    $bar->advance();
                    continue;
                }

                $extractedInfo = $extractionResult['data'] ?? null;

                if (!$extractedInfo) {
                    // Try to extract description field directly from text/html as fallback
                    $descriptionField = $this->extractDescriptionFieldDirectly($email->text_body ?? '', $email->html_body ?? '');
                    if ($descriptionField && strlen($descriptionField) === 43) {
                        $email->update(['description_field' => $descriptionField]);
                        $successCount++;
                    } else {
                        $failedCount++;
                    }
                    $bar->advance();
                    continue;
                }

                // Extract description_field from the result
                $descriptionField = $extractedInfo['description_field'] ?? null;

                // If not in extractedInfo, try to extract directly from text/html
                if (!$descriptionField || strlen($descriptionField) !== 43) {
                    if ($debug) {
                        $this->line("  ⚠️  description_field not in extractedInfo, trying direct extraction...");
                    }
                    $descriptionField = $this->extractDescriptionFieldDirectly($email->text_body ?? '', $email->html_body ?? '');
                    if ($debug && $descriptionField) {
                        $this->line("  ✅ Found via direct extraction: " . substr($descriptionField, 0, 20) . "...");
                    } elseif ($debug) {
                        $this->line("  ❌ Direct extraction also failed");
                    }
                }

                if ($descriptionField && strlen($descriptionField) === 43) {
                    if ($debug) {
                        $this->line("  ✅ Success! Description field: {$descriptionField}");
                    }
                    // Update the email with the description field
                    $email->update([
                        'description_field' => $descriptionField,
                    ]);

                    // Also update other fields if they're missing but were extracted
                    $updates = [];
                    
                    if (!$email->account_number && isset($extractedInfo['account_number'])) {
                        $updates['account_number'] = $extractedInfo['account_number'];
                    }
                    
                    if (!$email->payer_account_number && isset($extractedInfo['payer_account_number'])) {
                        // Note: payer_account_number might not be a column, but we'll try
                        // Actually, we don't have this column in processed_emails, so skip
                    }
                    
                    if (!$email->amount && isset($extractedInfo['amount']) && $extractedInfo['amount'] > 0) {
                        $updates['amount'] = $extractedInfo['amount'];
                    }
                    
                    if (!$email->sender_name && isset($extractedInfo['sender_name'])) {
                        $updates['sender_name'] = $extractedInfo['sender_name'];
                    }

                    if (!empty($updates)) {
                        $email->update($updates);
                    }

                    $successCount++;
                } else {
                    if ($debug) {
                        $this->line("  ❌ Failed: " . ($descriptionField ? "Found but length is " . strlen($descriptionField) : "Not found"));
                        // Show what we're working with
                        $textLen = strlen($email->text_body ?? '');
                        $htmlLen = strlen($email->html_body ?? '');
                        $this->line("  📄 text_body length: {$textLen}, html_body length: {$htmlLen}");
                        
                        // Try to find ANY digits in text_body
                        if ($email->text_body && preg_match('/(\d{20,})/', $email->text_body, $anyDigits)) {
                            $this->line("  🔍 Found " . strlen($anyDigits[1]) . " consecutive digits in text_body: " . substr($anyDigits[1], 0, 30) . "...");
                        }
                        
                        // Check if description field exists but in different format
                        if ($email->text_body && preg_match('/description[\s]*:[\s]*([^\n\r]{20,})/i', $email->text_body, $descLine)) {
                            $this->line("  📝 Description line found: " . substr(trim($descLine[1]), 0, 60) . "...");
                        }
                    }
                    $failedCount++;
                }

                $bar->advance();
            } catch (\Exception $e) {
                Log::error('Failed to re-extract description field', [
                    'email_id' => $email->id,
                    'error' => $e->getMessage(),
                ]);
                $failedCount++;
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);

        // Summary
        $this->info('📊 Summary:');
        $this->table(
            ['Status', 'Count'],
            [
                ['✅ Success', $successCount],
                ['❌ Failed', $failedCount],
                ['⏭️  Skipped', $skippedCount],
                ['📧 Total Processed', $emailsToProcess->count()],
            ]
        );

        if ($successCount > 0) {
            $this->info("✅ Successfully extracted description fields for {$successCount} email(s)!");
        }

        if ($failedCount > 0) {
            $this->warn("⚠️  Failed to extract description fields for {$failedCount} email(s).");
            $this->info('   These emails may not have the GTBank description field format.');
        }

        if ($totalEmails > $limit) {
            $remaining = $totalEmails - $limit;
            $this->info("💡 {$remaining} more email(s) remaining. Run the command again to process more.");
        }

        return 0;
    }

    /**
     * Extract description field directly from text/html (fallback method)
     * This is a SIMPLE, DIRECT extraction - just find 43 consecutive digits after "Description :"
     */
    protected function extractDescriptionFieldDirectly(?string $textBody, ?string $htmlBody): ?string
    {
        // Try text_body first (cleaner)
        if ($textBody) {
            // Decode quoted-printable first (e.g., =20 becomes space)
            $textBody = $this->decodeQuotedPrintable($textBody);
            
            // Pattern 1: Direct match - "Description : " followed by exactly 43 digits
            // This is the SIMPLEST pattern - just match the digits
            if (preg_match('/description[\s]*:[\s]*(\d{43})(?:\s|FROM|$)/i', $textBody, $matches)) {
                return trim($matches[1]);
            }
            
            // Pattern 2: Find description line, then extract 43 consecutive digits from anywhere in that line
            if (preg_match('/description[\s]*:[\s]*([^\n\r]+)/i', $textBody, $lineMatches)) {
                $descLine = trim($lineMatches[1]);
                // Find exactly 43 consecutive digits anywhere in the description line
                if (preg_match('/(\d{43})/', $descLine, $digitMatches)) {
                    $found = trim($digitMatches[1]);
                    // Verify it's exactly 43 digits
                    if (strlen($found) === 43 && ctype_digit($found)) {
                        return $found;
                    }
                }
            }
            
            // Pattern 3: Ultra-simple - just find 43 consecutive digits anywhere in text_body
            // This is a last resort fallback
            if (preg_match('/(\d{43})/', $textBody, $digitMatches)) {
                $found = trim($digitMatches[1]);
                if (strlen($found) === 43 && ctype_digit($found)) {
                    return $found;
                }
            }
        }

        // Try html_body (convert to plain text first)
        if ($htmlBody) {
            // Decode quoted-printable first
            $htmlBody = $this->decodeQuotedPrintable($htmlBody);
            // Decode HTML entities
            $htmlBody = html_entity_decode($htmlBody, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            // Convert to plain text
            $plainText = strip_tags($htmlBody);
            $plainText = preg_replace('/\s+/', ' ', $plainText);

            // Pattern 1: Direct match - "Description : " followed by exactly 43 digits
            if (preg_match('/description[\s]*:[\s]*(\d{43})(?:\s|FROM|$)/i', $plainText, $matches)) {
                return trim($matches[1]);
            }
            
            // Pattern 2: Find description line, then extract 43 consecutive digits
            if (preg_match('/description[\s]*:[\s]*([^\n\r]+)/i', $plainText, $lineMatches)) {
                $descLine = trim($lineMatches[1]);
                if (preg_match('/(\d{43})/', $descLine, $digitMatches)) {
                    $found = trim($digitMatches[1]);
                    if (strlen($found) === 43 && ctype_digit($found)) {
                        return $found;
                    }
                }
            }
            
            // Pattern 3: Ultra-simple - just find 43 consecutive digits anywhere
            if (preg_match('/(\d{43})/', $plainText, $digitMatches)) {
                $found = trim($digitMatches[1]);
                if (strlen($found) === 43 && ctype_digit($found)) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * Decode quoted-printable encoding
     */
    protected function decodeQuotedPrintable(string $text): string
    {
        // Replace =XX with corresponding character (where XX is hex)
        $text = preg_replace_callback('/=([0-9A-F]{2})/i', function ($matches) {
            return chr(hexdec($matches[1]));
        }, $text);
        
        // Replace soft line breaks (= at end of line)
        $text = preg_replace('/=\r?\n/', '', $text);
        
        return $text;
    }
}
