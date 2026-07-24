<?php

namespace App\Services;

use App\Models\Business;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\MevonPay\PrivateAccountProvisionService;

/**
 * When business KYC gates pass, queue permanent pay-in account creation via /V1/pivateaccount.
 */
class BusinessRubiesKycAutoVerificationService
{
    public function __construct(
        private PrivateAccountProvisionService $provision,
    ) {}

    /**
     * @return array{verified: bool, attempted: bool, skipped: bool, message: string}
     */
    public function attemptAfterSubmission(Business $business): array
    {
        return $this->attemptQueueProvision($business, 'after_submission');
    }

    /**
     * @return array{verified: bool, attempted: bool, skipped: bool, message: string}
     */
    public function attemptIndependentPermanentAccount(Business $business): array
    {
        return $this->attemptQueueProvision($business, 'independent_request');
    }

    /**
     * @return array{verified: bool, attempted: bool, skipped: bool, message: string}
     */
    private function attemptQueueProvision(Business $business, string $context): array
    {
        $business->refresh();

        if (! empty($business->rubies_business_account_number)) {
            return [
                'verified' => $business->hasAllKycDocumentsApproved(),
                'attempted' => false,
                'skipped' => true,
                'message' => '',
            ];
        }

        $readiness = $this->provision->businessReadiness($business);
        if (! $readiness['ready']) {
            $inProgress = in_array(
                'Pay-in account creation is already in progress.',
                $readiness['missing'],
                true
            );

            if ($inProgress) {
                return [
                    'verified' => false,
                    'attempted' => true,
                    'skipped' => false,
                    'message' => 'Pay-in account is being created. Refresh this page in a moment.',
                ];
            }

            return [
                'verified' => false,
                'attempted' => false,
                'skipped' => true,
                'message' => $readiness['missing'][0] ?? '',
            ];
        }

        $lock = Cache::lock('business_private_account_queue:'.$business->id, 45);

        try {
            return $lock->block(15, function () use ($business, $context) {
                $business->refresh();

                if (! empty($business->rubies_business_account_number)) {
                    return [
                        'verified' => $business->hasAllKycDocumentsApproved(),
                        'attempted' => false,
                        'skipped' => true,
                        'message' => '',
                    ];
                }

                $result = $this->provision->dispatchBusinessIfReady($business);
                if (! $result['dispatched']) {
                    return [
                        'verified' => false,
                        'attempted' => false,
                        'skipped' => true,
                        'message' => $result['message'],
                    ];
                }

                Log::info('business.private_account_queued', [
                    'business_id' => $business->id,
                    'context' => $context,
                ]);

                return [
                    'verified' => false,
                    'attempted' => true,
                    'skipped' => false,
                    'message' => $result['message'],
                ];
            });
        } catch (LockTimeoutException) {
            Log::notice('business.private_account_queue.lock_timeout', ['business_id' => $business->id]);

            return [
                'verified' => false,
                'attempted' => true,
                'skipped' => false,
                'message' => 'Pay-in account setup is processing. Refresh this page in a moment.',
            ];
        }
    }
}
