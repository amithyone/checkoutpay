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
            'amount_field_label' => 'Amount',
            'sender_name_field_label' => 'Description',
            'account_number_field_label' => 'Account Number',
            'amount_pattern' => '/<td[^>]*>[\s]*amount[\s:]*<\/td>\s*<td[^>]*>[\s]*(?:ngn|naira|₦)?\s*([\d,]+\.?\d*)[\s]*<\/td>/i',
            'sender_name_pattern' => '/from\s+([A-Z][A-Z\s]+?)\s+to/i',
            'account_number_pattern' => '/<td[^>]*>[\s]*(?:account\s*number|account)[\s:]*<\/td>\s*<td[^>]*>[\s]*(\d+)[\s]*<\/td>/i',
            'extraction_notes' => 'GTBank (GeNS) Transaction Notification. Extract from HTML table: Account Number, Amount (strip NGN/₦), Description (FROM <NAME> TO pattern), Value Date, Transaction Type (CREDIT/DEBIT).',
            'is_active' => true,
            'priority' => 100, // High priority
        ]);

        $this->command->info('GTBank template created successfully!');
    }
}
