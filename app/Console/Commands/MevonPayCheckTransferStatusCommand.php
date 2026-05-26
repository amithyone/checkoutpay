<?php

namespace App\Console\Commands;

use App\Services\MevonPay\MevonPayTransferStatusService;
use Illuminate\Console\Command;

class MevonPayCheckTransferStatusCommand extends Command
{
    protected $signature = 'mevonpay:check-transfer-status {reference : Payout reference / session id}';

    protected $description = 'Query MevonPay transfer status (POST /V1/tsk) for a reference';

    public function handle(MevonPayTransferStatusService $service): int
    {
        $reference = (string) $this->argument('reference');
        $result = $service->checkStatus($reference);

        $this->line('Available: '.($result['available'] ? 'yes' : 'no'));
        $this->line('Message: '.($result['message'] ?? ''));
        if (isset($result['bucket'])) {
            $this->line('Bucket: '.$result['bucket']);
        }
        if (isset($result['response_code'])) {
            $this->line('Response code: '.($result['response_code'] ?? '—'));
        }
        if (isset($result['transaction_status'])) {
            $this->line('Transaction status: '.($result['transaction_status'] ?? '—'));
        }
        if (! empty($result['details']) && is_array($result['details'])) {
            $this->newLine();
            $this->info('Details:');
            $this->line(json_encode($result['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return ($result['available'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
