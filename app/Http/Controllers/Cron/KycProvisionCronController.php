<?php

namespace App\Http\Controllers\Cron;

use App\Http\Controllers\Controller;
use App\Jobs\CreateBusinessPrivateAccountJob;
use App\Jobs\CreatePersonalPrivateAccountJob;
use App\Models\Business;
use App\Models\WhatsappWallet;
use App\Services\MevonPay\PrivateAccountProvisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class KycProvisionCronController extends Controller
{
    public function process(Request $request, PrivateAccountProvisionService $provision): JsonResponse
    {
        $start = microtime(true);
        $queueConnection = (string) config('queue.default', 'sync');

        if ($queueConnection === 'sync') {
            return response()->json([
                'success' => false,
                'message' => 'QUEUE_CONNECTION=sync — set QUEUE_CONNECTION=database on live so KYC jobs can run in the background.',
                'timestamp' => now()->toDateTimeString(),
            ], 503);
        }

        $before = $this->pendingCounts();

        $redispatch = $provision->redispatchOrphanedQueued();

        $queueOutput = null;
        $queueError = null;

        try {
            Artisan::call('queue:work', [
                'connection' => $queueConnection,
                '--queue' => PrivateAccountProvisionService::QUEUE_KYC_PROVISION,
                '--stop-when-empty' => true,
                '--max-jobs' => 10,
                '--max-time' => 110,
                '--timeout' => 120,
                '--tries' => 3,
            ]);
            $queueOutput = trim(Artisan::output()) ?: null;
        } catch (\Throwable $e) {
            $queueError = $e->getMessage();
            Log::error('kyc_provision.cron_queue_failed', ['error' => $queueError]);
        }

        $after = $this->pendingCounts();

        return response()->json([
            'success' => $queueError === null,
            'message' => $queueError === null
                ? 'KYC / Rubies account queue processed'
                : 'Queue worker error: '.$queueError,
            'method' => 'process_kyc_queue',
            'timestamp' => now()->toDateTimeString(),
            'execution_time_seconds' => round(microtime(true) - $start, 2),
            'queue_connection' => $queueConnection,
            'queue' => PrivateAccountProvisionService::QUEUE_KYC_PROVISION,
            'redispatch' => $redispatch,
            'before' => $before,
            'after' => $after,
            'jobs_processed_hint' => max(0, $before['jobs_in_table'] - $after['jobs_in_table']),
            'output' => $queueOutput,
        ], $queueError === null ? 200 : 500);
    }

    /**
     * @return array<string, int|bool>
     */
    private function pendingCounts(): array
    {
        $walletQueued = 0;
        $walletProcessing = 0;
        $walletFailed = 0;
        $businessQueued = 0;
        $businessProcessing = 0;
        $businessFailed = 0;
        $jobsInTable = 0;

        if (Schema::hasColumn('whatsapp_wallets', 'private_account_provision_status')) {
            $walletQueued = WhatsappWallet::query()
                ->where('private_account_provision_status', PrivateAccountProvisionService::STATUS_QUEUED)
                ->count();
            $walletProcessing = WhatsappWallet::query()
                ->where('private_account_provision_status', PrivateAccountProvisionService::STATUS_PROCESSING)
                ->count();
            $walletFailed = WhatsappWallet::query()
                ->where('private_account_provision_status', PrivateAccountProvisionService::STATUS_FAILED)
                ->count();
        }

        if (Schema::hasColumn('businesses', 'rubies_account_provision_status')) {
            $businessQueued = Business::query()
                ->where('rubies_account_provision_status', PrivateAccountProvisionService::STATUS_QUEUED)
                ->count();
            $businessProcessing = Business::query()
                ->where('rubies_account_provision_status', PrivateAccountProvisionService::STATUS_PROCESSING)
                ->count();
            $businessFailed = Business::query()
                ->where('rubies_account_provision_status', PrivateAccountProvisionService::STATUS_FAILED)
                ->count();
        }

        if (Schema::hasTable('jobs')) {
            $personalJob = CreatePersonalPrivateAccountJob::class;
            $businessJob = CreateBusinessPrivateAccountJob::class;
            $jobsInTable = (int) DB::table('jobs')
                ->where(function ($q) use ($personalJob, $businessJob) {
                    $q->where('payload', 'like', '%'.$personalJob.'%')
                        ->orWhere('payload', 'like', '%'.$businessJob.'%');
                })
                ->count();
        }

        return [
            'wallet_queued' => $walletQueued,
            'wallet_processing' => $walletProcessing,
            'wallet_failed' => $walletFailed,
            'business_queued' => $businessQueued,
            'business_processing' => $businessProcessing,
            'business_failed' => $businessFailed,
            'jobs_in_table' => $jobsInTable,
            'mevon_configured' => app(PrivateAccountProvisionService::class)->isConfigured(),
        ];
    }
}
