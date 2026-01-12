<?php

namespace App\Console\Commands;

use App\Models\ProcessedEmail;
use App\Services\SenderNameExtractor;
use App\Services\AdvancedNameExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExtractMissingNames extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:extract-missing-names 
                            {--limit=100 : Maximum number of emails to process}
                            {--force : Force re-extraction even if name exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Extract missing sender names from processed emails text_body using description field pattern';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = (int) $this->option('limit');
        $force = $this->option('force');
        
        $this->info("Starting name extraction from processed emails...");
        
        // Get ALL emails without sender_name (not just unmatched ones)
        $query = ProcessedEmail::whereNotNull('text_body')
            ->where('text_body', '!=', '');
        
        if (!$force) {
            $query->where(function($q) {
                $q->whereNull('sender_name')
                  ->orWhere('sender_name', '')
                  ->orWhereRaw('LENGTH(TRIM(sender_name)) - LENGTH(REPLACE(TRIM(sender_name), " ", "")) = 0'); // Single word
            });
        }
        
        $emails = $query->limit($limit)->get();
        
        $this->info("Found {$emails->count()} email(s) to process");
        
        $extracted = 0;
        $updated = 0;
        $failed = 0;
        
        $advancedExtractor = new AdvancedNameExtractor();
        
        foreach ($emails as $email) {
            try {
                $originalName = $email->sender_name;
                $extractedName = null;
                
                // Use AdvancedNameExtractor for comprehensive extraction
                $extractedName = $advancedExtractor->extract(
                    $email->text_body ?? '',
                    $email->html_body ?? '',
                    $email->subject ?? ''
                );
                
                // Only update if we found a name and it's different/better than existing
                if (!empty($extractedName)) {
                    // If existing name is single word and extracted name is multiple words, use extracted
                    $shouldUpdate = false;
                    
                    if (empty($originalName)) {
                        $shouldUpdate = true;
                    } elseif (str_word_count($originalName) === 1 && str_word_count($extractedName) > 1) {
                        // Existing is single word, extracted is multiple words - use extracted
                        $shouldUpdate = true;
                    } elseif (empty($originalName) || $originalName !== $extractedName) {
                        // No existing name or different name
                        $shouldUpdate = true;
                    }
                    
                    if ($shouldUpdate) {
                        // Get current extracted_data
                        $extractedData = $email->extracted_data ?? [];
                        $extractedData['sender_name'] = $extractedName;
                        
                        // Also update if nested in 'data' key
                        if (isset($extractedData['data']) && is_array($extractedData['data'])) {
                            $extractedData['data']['sender_name'] = $extractedName;
                        }
                        
                        $email->update([
                            'sender_name' => $extractedName,
                            'extracted_data' => $extractedData,
                        ]);
                        
                        $updated++;
                        $this->line("✓ Email #{$email->id}: Extracted '{$extractedName}' " . 
                                   ($originalName ? "(was: '{$originalName}')" : "(was empty)"));
                        
                        Log::info('Extracted sender name from processed email', [
                            'email_id' => $email->id,
                            'original_name' => $originalName,
                            'extracted_name' => $extractedName,
                            'method' => 'cron_extraction',
                        ]);
                    } else {
                        $this->line("- Email #{$email->id}: Already has name '{$originalName}' (skipped)");
                    }
                } else {
                    $extracted++;
                    $this->line("✗ Email #{$email->id}: Could not extract name");
                }
            } catch (\Exception $e) {
                $failed++;
                $this->error("✗ Email #{$email->id}: Error - " . $e->getMessage());
                Log::error('Failed to extract name from processed email', [
                    'email_id' => $email->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        $this->newLine();
        $this->info("Extraction complete!");
        $this->info("  - Updated: {$updated}");
        $this->info("  - Could not extract: {$extracted}");
        $this->info("  - Failed: {$failed}");
        
        return 0;
    }
}
