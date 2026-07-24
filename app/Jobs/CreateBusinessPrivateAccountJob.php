<?php

namespace App\Jobs;

use App\Models\Business;
use App\Services\MevonPay\MevonPrivateAccountService;
use App\Services\MevonPay\PrivateAccountProvisionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateBusinessPrivateAccountJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public int $businessId,
    ) {}

    public function handle(
        MevonPrivateAccountService $privateAccount,
        PrivateAccountProvisionService $provision,
    ): void {
        $business = Business::query()->find($this->businessId);
        if ($business === null) {
            return;
        }

        if (! empty($business->rubies_business_account_number)) {
            return;
        }

        $business->update([
            'rubies_account_provision_status' => PrivateAccountProvisionService::STATUS_PROCESSING,
        ]);

        $identity = $provision->verifiedBusinessIdentity($business);
        if ($identity === null) {
            $this->markFailed($business, 'No verified BVN or NIN on file.');

            return;
        }

        $dob = $business->rubies_signatory_dob;
        $dobYmd = $dob instanceof \DateTimeInterface ? $dob->format('Y-m-d') : (string) $dob;
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dobYmd)) {
            $this->markFailed($business, 'Signatory date of birth is missing or invalid.');

            return;
        }

        try {
            $va = $privateAccount->createBusinessAccount(
                businessName: trim((string) $business->name),
                cac: strtoupper(trim((string) $business->cac_registration_number)),
                phoneLocal11: trim((string) $business->phone),
                dobYmd: $dobYmd,
                email: strtolower(trim((string) $business->email)),
                bvn11: $identity['bvn'],
                nin11: $identity['nin'],
            );
        } catch (\Throwable $e) {
            Log::warning('private_account.business_job_failed', [
                'business_id' => $business->id,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);
            $this->markFailed($business, $e->getMessage());
            throw $e;
        }

        $business->refresh();
        if (! empty($business->rubies_business_account_number)) {
            return;
        }

        $business->update([
            'rubies_business_account_number' => $va['account_number'],
            'rubies_business_account_name' => $va['account_name'] ?: null,
            'rubies_business_bank_name' => $va['bank_name'] ?: null,
            'rubies_business_bank_code' => $va['bank_code'] ?: null,
            'rubies_business_reference' => $va['reference'] !== '' ? $va['reference'] : $business->rubies_business_reference,
            'rubies_business_account_created_at' => now(),
            'rubies_account_provision_status' => PrivateAccountProvisionService::STATUS_COMPLETED,
            'rubies_account_provision_error' => null,
        ]);

        Log::info('private_account.business_completed', [
            'business_id' => $business->id,
            'account_suffix' => substr((string) $va['account_number'], -4),
        ]);
    }

    public function failed(?\Throwable $exception): void
    {
        $business = Business::query()->find($this->businessId);
        if ($business === null || ! empty($business->rubies_business_account_number)) {
            return;
        }

        $message = $exception?->getMessage() ?? 'Account provisioning failed after retries.';
        $this->markFailed($business, $message);
    }

    private function markFailed(Business $business, string $message): void
    {
        $business->update([
            'rubies_account_provision_status' => PrivateAccountProvisionService::STATUS_FAILED,
            'rubies_account_provision_error' => $message,
        ]);
    }
}
