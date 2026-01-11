<?php

namespace App\Console\Commands;

use App\Models\ProcessedEmail;
use Illuminate\Console\Command;

class ParseDescriptionFields extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:parse-description-fields 
                            {--limit=100 : Maximum number of emails to process}
                            {--force : Update even if account_number already exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Parse existing description_field column to extract and store account numbers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = (int) $this->option('limit');
        $force = $this->option('force');

        $this->info('🔄 Parsing description fields to extract account numbers...');
        $this->newLine();

        // Find emails that have description_field but might be missing account_number
        $query = ProcessedEmail::whereNotNull('description_field');
        
        if (!$force) {
            // Only process emails where account_number is NULL or empty
            $query->where(function($q) {
                $q->whereNull('account_number')
                  ->orWhere('account_number', '');
            });
        }

        $totalEmails = $query->count();
        $emailsToProcess = $query->limit($limit)->get();

        if ($emailsToProcess->isEmpty()) {
            $this->info('✅ No emails found to process.');
            if (!$force) {
                $this->info('   (Use --force to process all emails with description_field)');
            }
            return 0;
        }

        $this->info("Found {$totalEmails} email(s) with description_field (processing {$emailsToProcess->count()})");
        $this->newLine();

        $bar = $this->output->createProgressBar($emailsToProcess->count());
        $bar->start();

        $successCount = 0;
        $skippedCount = 0;

        foreach ($emailsToProcess as $email) {
            try {
                // Skip if account_number already exists and not forcing
                if (!$force && $email->account_number) {
                    $skippedCount++;
                    $bar->advance();
                    continue;
                }

                // Parse the description field
                $parsedData = $this->parseDescriptionField($email->description_field);

                if ($parsedData['account_number']) {
                    // Update extracted_data to include parsed description field data
                    $currentExtractedData = $email->extracted_data ?? [];
                    $currentExtractedData['description_field'] = $email->description_field;
                    $currentExtractedData['account_number'] = $parsedData['account_number'];
                    $currentExtractedData['payer_account_number'] = $parsedData['payer_account_number'];
                    // SKIP amount_from_description - not reliable, use amount field instead
                    $currentExtractedData['date_from_description'] = $parsedData['extracted_date'];

                    // Prepare updates
                    $updates = [
                        'account_number' => $parsedData['account_number'],
                        'extracted_data' => $currentExtractedData,
                    ];

                    // SKIP updating amount from description field - not reliable
                    // Use amount field from email extraction instead

                    $email->update($updates);
                    $successCount++;
                } else {
                    $skippedCount++;
                }

                $bar->advance();
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to parse description field', [
                    'email_id' => $email->id,
                    'error' => $e->getMessage(),
                ]);
                $skippedCount++;
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
                ['⏭️  Skipped', $skippedCount],
                ['📧 Total Processed', $emailsToProcess->count()],
            ]
        );

        if ($successCount > 0) {
            $this->info("✅ Successfully parsed and updated {$successCount} email(s)!");
        }

        if ($totalEmails > $limit) {
            $remaining = $totalEmails - $limit;
            $this->info("💡 {$remaining} more email(s) remaining. Run the command again to process more.");
        }

        return 0;
    }

    /**
     * Parse description field to extract account numbers, amount, date
     * Format: recipient_account(10) + payer_account(10) + amount(6) + date(8) + unknown(9) = 43 digits
     * 
     * IMPORTANT: Amount extraction from description field is NOT reliable
     * - The 6 digits after account numbers may not always be the amount
     * - We already have a reliable amount field from email extraction
     * - So we'll skip amount extraction from description field to avoid incorrect values
     * 
     * Also handles 30-digit and 42-digit formats
     */
    protected function parseDescriptionField(?string $descriptionField): array
    {
        $result = [
            'account_number' => null,        // First 10 digits - recipient account (where payment was sent TO)
            'payer_account_number' => null, // Next 10 digits - sender account (where payment was sent FROM)
            'amount' => null,                // NOT extracted from description - use amount field instead
            'extracted_date' => null,
        ];

        if (!$descriptionField) {
            return $result;
        }

        $length = strlen($descriptionField);

        // Handle 43-digit format (current format)
        if ($length >= 43) {
            if (preg_match('/^(\d{10})(\d{10})(\d{6})(\d{8})(\d{9})/', $descriptionField, $matches)) {
                $result['account_number'] = trim($matches[1]);        // First 10 digits
                $result['payer_account_number'] = trim($matches[2]);  // Next 10 digits
                // SKIP amount extraction - not reliable, use amount field instead
                // $result['amount'] = (float) ($matches[3] / 100);      // Amount (6 digits, divide by 100)
                $dateStr = $matches[4];                                // Date YYYYMMDD (8 digits)
                if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                    $result['extracted_date'] = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
                }
            }
        }
        // Handle 42-digit format (missing 1 digit - pad with 0)
        elseif ($length == 42) {
            $padded = $descriptionField . '0';
            if (preg_match('/^(\d{10})(\d{10})(\d{6})(\d{8})(\d{9})/', $padded, $matches)) {
                $result['account_number'] = trim($matches[1]);
                $result['payer_account_number'] = trim($matches[2]);
                // SKIP amount extraction - not reliable
                $dateStr = $matches[4];
                if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                    $result['extracted_date'] = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
                }
            }
        }
        // Handle 30-digit format (old format - just extract first 20 digits as account numbers)
        elseif ($length >= 30) {
            if (preg_match('/^(\d{10})(\d{10})/', $descriptionField, $matches)) {
                $result['account_number'] = trim($matches[1]);
                $result['payer_account_number'] = trim($matches[2]);
                // SKIP amount extraction - not reliable for 30-digit format either
            }
        }

        return $result;
    }
}
