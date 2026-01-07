<?php

namespace App\Console\Commands;

use App\Jobs\ProcessEmailPayment;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestEmailProcessing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:test-email-processing {--transaction-id= : Specific transaction ID to test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test email processing with a sample email';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('🧪 Testing Email Processing');
        $this->newLine();

        // Check for pending payments
        $pendingPayments = Payment::pending()->get();
        
        if ($pendingPayments->isEmpty()) {
            $this->warn('⚠️  No pending payments found.');
            $this->info('💡 Create a payment request first, then send a test email.');
            return;
        }

        $this->info("Found {$pendingPayments->count()} pending payment(s):");
        $this->newLine();

        $payment = null;
        if ($this->option('transaction-id')) {
            $payment = Payment::where('transaction_id', $this->option('transaction-id'))->first();
            if (!$payment || $payment->status !== Payment::STATUS_PENDING) {
                $this->error("❌ Payment not found or not pending: {$this->option('transaction-id')}");
                return;
            }
        } else {
            // Show list of pending payments
            $headers = ['Transaction ID', 'Amount', 'Payer Name', 'Business', 'Created At'];
            $rows = [];
            foreach ($pendingPayments as $p) {
                $rows[] = [
                    $p->transaction_id,
                    '₦' . number_format($p->amount, 2),
                    $p->payer_name ?? 'N/A',
                    $p->business->name ?? 'N/A',
                    $p->created_at->format('Y-m-d H:i:s'),
                ];
            }
            $this->table($headers, $rows);
            $this->newLine();
            
            $payment = $pendingPayments->first();
            $this->info("Using first pending payment: {$payment->transaction_id}");
        }

        $this->newLine();
        $this->info('📧 Simulating email notification...');
        $this->newLine();

        // Create sample email data
        $payerName = $payment->payer_name ?? 'Customer';
        $emailData = [
            'subject' => 'Bank Transfer Notification - ' . $payment->amount,
            'from' => 'bank@example.com',
            'text' => "You have received a transfer of ₦{$payment->amount} from {$payerName}",
            'html' => "<p>You have received a transfer of ₦{$payment->amount} from {$payerName}</p>",
            'date' => now()->toDateTimeString(),
            'email_account_id' => $payment->business->email_account_id ?? null,
        ];

        $this->line('Email Subject: ' . $emailData['subject']);
        $this->line('Email From: ' . $emailData['from']);
        $this->line('Email Amount: ₦' . number_format($payment->amount, 2));
        $this->line('Email Payer: ' . ($payment->payer_name ?? 'N/A'));
        $this->newLine();

        // Process email
        $this->info('🔄 Processing email...');
        try {
            ProcessEmailPayment::dispatchSync($emailData);
            
            // Refresh payment to check status
            $payment->refresh();
            
            if ($payment->status === Payment::STATUS_APPROVED) {
                $this->info('✅ Email processed successfully!');
                $this->info("✅ Payment {$payment->transaction_id} has been APPROVED");
                $this->newLine();
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['Transaction ID', $payment->transaction_id],
                        ['Status', 'APPROVED ✅'],
                        ['Amount', '₦' . number_format($payment->amount, 2)],
                        ['Approved At', $payment->approved_at->format('Y-m-d H:i:s')],
                    ]
                );
            } else {
                $this->warn('⚠️  Email processed but payment not matched.');
                $this->warn("Payment status: {$payment->status}");
                $this->newLine();
                $this->info('Possible reasons:');
                $this->line('- Amount mismatch');
                $this->line('- Payer name mismatch');
                $this->line('- Email format not recognized');
            }
        } catch (\Exception $e) {
            $this->error('❌ Error processing email: ' . $e->getMessage());
            Log::error('Test email processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
