<?php

namespace Database\Seeders;

use App\Models\BankEmailTemplate;
use Illuminate\Database\Seeder;

class GtbankTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if GTBank template already exists
        $existing = BankEmailTemplate::where('bank_name', 'GTBank')
            ->orWhere('bank_name', 'Guaranty Trust Bank')
            ->first();
        
        if ($existing) {
            $this->command->info('GTBank template already exists. Skipping...');
            return;
        }

        BankEmailTemplate::create([
            'bank_name' => 'GTBank',
            'sender_domain' => '@gtbank.com',
            'sender_email' => 'GeNS@gtbank.com',
            'amount_field_label' => 'Amount',
            'sender_name_field_label' => 'Description',
            'account_number_field_label' => 'Account Number',
            // HTML table patterns
            'amount_pattern' => '/(?s)<td[^>]*>[\s]*amount[\s:]*<\/td>\s*<td[^>]*>[\s:]*<\/td>\s*<td[^>]*>[\s]*(?:ngn|naira|₦|NGN)[\s]+([\d,]+\.?\d*)[\s]*<\/td>/i',
            'account_number_pattern' => '/(?s)<td[^>]*>[\s]*account\s*number[\s:]*<\/td>\s*<td[^>]*>[\s:]*<\/td>\s*<td[^>]*>[\s]*(\d+)[\s]*<\/td>/i',
            // Text-based patterns (for forwarded emails and plain text)
            // Amount: "Amount : NGN 1000" or "Amount: NGN 1000"
            // Sender Name: "Description : ...CODE-NAME TRF FOR..." or "Description : ...AMITHY ONE M TRF FOR..."
            // Account Number: "Account Number : 3002156642"
            'sender_name_pattern' => '/(?:description|description[\s:]+).*?(?:[\d\-]+\s*-\s*)?([A-Z][A-Z\s]{2,}?)\s+(?:TRF|TRANSFER|FOR|TO)|from\s+([A-Z][A-Z\s]+?)\s+to/i',
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
Example: "090405260110014006799532206126-AMITHY ONE M TRF FOR CUSTOMER..." → extract "AMITHY ONE M"',
            'is_active' => true,
            'priority' => 100, // High priority
        ]);

        $this->command->info('GTBank template created successfully!');
    }
}
