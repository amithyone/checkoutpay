<?php

namespace App\Console\Commands;

use App\Models\BankEmailTemplate;
use Illuminate\Console\Command;

class UpdateGtbankTemplate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'template:update-gtbank';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update GTBank email template with improved extraction patterns';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $template = BankEmailTemplate::where('bank_name', 'GTBank')
            ->orWhere('bank_name', 'Guaranty Trust Bank')
            ->first();

        if (!$template) {
            $this->error('GTBank template not found. Run php artisan db:seed --class=GtbankTemplateSeeder first.');
            return 1;
        }

        $this->info('Updating GTBank template...');

        $template->update([
            'sender_email' => 'GeNS@gtbank.com',
            'sender_domain' => '@gtbank.com',
            'amount_field_label' => 'Amount',
            'sender_name_field_label' => 'Description',
            'account_number_field_label' => 'Account Number',
            // HTML table patterns (improved)
            'amount_pattern' => '/(?s)<td[^>]*>[\s]*amount[\s:]*<\/td>\s*<td[^>]*>[\s:]*<\/td>\s*<td[^>]*>[\s]*(?:ngn|naira|₦|NGN)[\s]+([\d,]+\.?\d*)[\s]*<\/td>/i',
            'account_number_pattern' => '/(?s)<td[^>]*>[\s]*account\s*number[\s:]*<\/td>\s*<td[^>]*>[\s:]*<\/td>\s*<td[^>]*>[\s]*(\d+)[\s]*<\/td>/i',
            // Text-based patterns (for forwarded emails)
            'sender_name_pattern' => '/(?:description[\s]*:[\s]*.*?[\d\-]+\s*-\s*|[\d\-]+\s*-\s*)([A-Z][A-Z\s]{2,}?)\s+(?:TRF|TRANSFER|FOR|TO)|from\s+([A-Z][A-Z\s]+?)\s+to/i',
            'extraction_notes' => 'GTBank (GeNS) Transaction Notification Format:

Fields to extract:
1. Account Number: "Account Number : 3002156642" (format: Account Number : <number>)
2. Transaction Location: "Transaction Location : 205" (optional)
3. Description: "Description : CODE-NAME TRF FOR..." (extract NAME from CODE-NAME TRF FOR pattern)
4. Amount: "Amount : NGN 1000" (format: Amount : NGN <number>)
5. Value Date: "Value Date : 2026-01-10" (optional)
6. Remarks: "Remarks : ..." (optional)
7. Time of Transaction: "Time of Transaction : 12:17:27 AM" (optional)

Text format (forwarded emails): Extract from plain text using "Field : Value" pattern
HTML format (original emails): Extract from HTML table structure with <td> tags

Pattern for sender name: Extract name from Description field that contains "CODE-NAME TRF FOR" or "AMITHY ONE M TRF FOR" format.
Example: "090405260110014006799532206126-AMITHY ONE M TRF FOR CUSTOMER..." → extract "AMITHY ONE M"

Extraction Strategy:
1. Try TEXT-based extraction first (normalize whitespace, handle forwarded emails)
2. Try HTML-based extraction (original email format)
3. Both strategies decode quoted-printable and HTML entities before extraction',
            'is_active' => true,
            'priority' => 100,
        ]);

        $this->info('✅ GTBank template updated successfully!');
        $this->info('Template ID: ' . $template->id);
        $this->info('Bank Name: ' . $template->bank_name);
        $this->info('Sender Domain: ' . $template->sender_domain);
        $this->info('Priority: ' . $template->priority);

        return 0;
    }
}
