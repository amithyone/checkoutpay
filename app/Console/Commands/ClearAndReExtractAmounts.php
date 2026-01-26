<?php

namespace App\Console\Commands;

use App\Models\ProcessedEmail;
use App\Services\PaymentMatchingService;
use App\Services\TransactionLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearAndReExtractAmounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:clear-and-re-extract {--limit= : Limit number of emails to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all amounts and sender names, then re-extract them to verify extraction accuracy';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Clearing all amounts and sender names...');
        
        // Clear all amounts and sender names
        $cleared = 0;
        ProcessedEmail::chunk(100, function ($emails) use (&$cleared) {
            foreach ($emails as $email) {
                $extractedData = $email->extracted_data ?? [];
                if (isset($extractedData['amount'])) {
                    unset($extractedData['amount']);
                }
                if (isset($extractedData['sender_name'])) {
                    unset($extractedData['sender_name']);
                }
                if (isset($extractedData['data']['amount'])) {
                    unset($extractedData['data']['amount']);
                }
                if (isset($extractedData['data']['sender_name'])) {
                    unset($extractedData['data']['sender_name']);
                }
                
                $email->update([
                    'amount' => null,
                    'sender_name' => null,
                    'extracted_data' => $extractedData,
                ]);
                $cleared++;
            }
        });
        
        $this->info("✅ Cleared {$cleared} emails");
        $this->newLine();
        
        $this->info('🔄 Re-extracting amounts and sender names...');
        
        $matchingService = new PaymentMatchingService(new TransactionLogService());
        
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $query = ProcessedEmail::query();
        
        if ($limit) {
            $query->limit($limit);
        }
        
        $emails = $query->get();
        $total = $emails->count();
        
        $this->info("Processing {$total} emails...");
        $this->newLine();
        
        $updated = 0;
        $withAmount = 0;
        $withSenderName = 0;
        $withBoth = 0;
        $withNeither = 0;
        $errors = 0;
        
        $bar = $this->output->createProgressBar($total);
        $bar->start();
        
        foreach ($emails as $email) {
            try {
                // Re-extract payment info
                $emailData = [
                    'subject' => $email->subject,
                    'from' => $email->from_email,
                    'text' => $email->text_body ?? '',
                    'html' => $email->html_body ?? '',
                    'date' => $email->email_date ? $email->email_date->toDateTimeString() : null,
                    'email_account_id' => $email->email_account_id,
                    'processed_email_id' => $email->id,
                ];
                
                $extractionResult = $matchingService->extractPaymentInfo($emailData);
                
                // Handle new format: ['data' => [...], 'method' => '...']
                $extractedInfo = null;
                $extractionMethod = null;
                if (is_array($extractionResult) && isset($extractionResult['data'])) {
                    $extractedInfo = $extractionResult['data'];
                    $extractionMethod = $extractionResult['method'] ?? null;
                } else {
                    $extractedInfo = $extractionResult;
                    $extractionMethod = 'unknown';
                }
                
                // Ensure extractedInfo is an array
                if (!is_array($extractedInfo)) {
                    $extractedInfo = [];
                }
                
                // Parse description field to extract account numbers and amount if available
                $descriptionField = $extractedInfo['description_field'] ?? null;
                $parsedFromDescription = $this->parseDescriptionField($descriptionField);
                
                // Use account_number from description field if not already set
                $accountNumber = $extractedInfo['account_number'] ?? $parsedFromDescription['account_number'] ?? null;
                
                // Update extracted_data to include parsed description field data
                if ($descriptionField) {
                    $extractedInfo['description_field'] = $descriptionField;
                    $extractedInfo['account_number'] = $parsedFromDescription['account_number'] ?? $extractedInfo['account_number'] ?? null;
                    $extractedInfo['payer_account_number'] = $parsedFromDescription['payer_account_number'] ?? $extractedInfo['payer_account_number'] ?? null;
                    // Use amount from description field if amount wasn't extracted from text
                    if (empty($extractedInfo['amount']) && !empty($parsedFromDescription['amount'])) {
                        $extractedInfo['amount'] = $parsedFromDescription['amount'];
                    }
                    $extractedInfo['date_from_description'] = $parsedFromDescription['extracted_date'] ?? null;
                }
                
                // Update email with extracted data
                $updateData = [
                    'extracted_data' => $extractedInfo,
                    'extraction_method' => $extractionMethod,
                ];
                
                $hasAmount = false;
                $hasSenderName = false;
                
                if (!empty($extractedInfo['amount']) && $extractedInfo['amount'] > 0) {
                    $updateData['amount'] = $extractedInfo['amount'];
                    $hasAmount = true;
                    $withAmount++;
                }
                
                if (!empty($extractedInfo['sender_name'])) {
                    $updateData['sender_name'] = strtolower(trim($extractedInfo['sender_name']));
                    $hasSenderName = true;
                    $withSenderName++;
                }
                
                if ($hasAmount && $hasSenderName) {
                    $withBoth++;
                } elseif (!$hasAmount && !$hasSenderName) {
                    $withNeither++;
                }
                
                if ($hasAmount || $hasSenderName) {
                    $updateData['account_number'] = $accountNumber;
                    $email->update($updateData);
                    $updated++;
                }
                
            } catch (\Exception $e) {
                $this->warn("\nError processing email ID {$email->id}: {$e->getMessage()}");
                $errors++;
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        
        // Calculate percentages
        $amountPercent = $total > 0 ? round(($withAmount / $total) * 100, 2) : 0;
        $senderNamePercent = $total > 0 ? round(($withSenderName / $total) * 100, 2) : 0;
        $bothPercent = $total > 0 ? round(($withBoth / $total) * 100, 2) : 0;
        $neitherPercent = $total > 0 ? round(($withNeither / $total) * 100, 2) : 0;
        
        $this->info("✅ Re-extraction complete!");
        $this->newLine();
        $this->table(
            ['Metric', 'Count', 'Percentage'],
            [
                ['Total Emails', $total, '100%'],
                ['With Amount', $withAmount, "{$amountPercent}%"],
                ['With Sender Name', $withSenderName, "{$senderNamePercent}%"],
                ['With Both', $withBoth, "{$bothPercent}%"],
                ['With Neither', $withNeither, "{$neitherPercent}%"],
                ['Updated', $updated, '-'],
                ['Errors', $errors, '-'],
            ]
        );
        
        $this->newLine();
        
        if ($amountPercent < 100 || $senderNamePercent < 100) {
            $this->warn("⚠️  Extraction accuracy is not 100%!");
            $this->warn("   Amount extraction: {$amountPercent}%");
            $this->warn("   Sender name extraction: {$senderNamePercent}%");
            $this->warn("   Something may be wrong with the extraction logic.");
        } else {
            $this->info("✅ Perfect! 100% extraction accuracy!");
        }
        
        return 0;
    }
    
    /**
     * Parse description field to extract account numbers, amount, date
     */
    protected function parseDescriptionField(?string $descriptionField): array
    {
        $result = [
            'account_number' => null,
            'payer_account_number' => null,
            'amount' => null,
            'extracted_date' => null,
        ];
        
        if (!$descriptionField) {
            return $result;
        }
        
        $length = strlen($descriptionField);
        
        // Handle 43-digit format
        if ($length >= 43) {
            if (preg_match('/^(\d{10})(\d{10})(\d{6})(\d{8})(\d{9})/', $descriptionField, $matches)) {
                $result['account_number'] = trim($matches[1]);
                $result['payer_account_number'] = trim($matches[2]);
                $result['amount'] = (float) ($matches[3] / 100);
                $dateStr = $matches[4];
                if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                    $result['extracted_date'] = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
                }
            }
        }
        // Handle 42-digit format
        elseif ($length == 42) {
            $padded = $descriptionField . '0';
            if (preg_match('/^(\d{10})(\d{10})(\d{6})(\d{8})(\d{9})/', $padded, $matches)) {
                $result['account_number'] = trim($matches[1]);
                $result['payer_account_number'] = trim($matches[2]);
                $result['amount'] = (float) ($matches[3] / 100);
                $dateStr = $matches[4];
                if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                    $result['extracted_date'] = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
                }
            }
        }
        // Handle 30-digit format
        elseif ($length >= 30) {
            if (preg_match('/^(\d{10})(\d{10})/', $descriptionField, $matches)) {
                $result['account_number'] = trim($matches[1]);
                $result['payer_account_number'] = trim($matches[2]);
                // Try to extract amount if available
                if (preg_match('/^(\d{10})(\d{10})(\d{6})/', $descriptionField, $amountMatches)) {
                    $result['amount'] = (float) ($amountMatches[3] / 100);
                }
            }
        }
        
        return $result;
    }
}
