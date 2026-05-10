<?php

namespace App\Services;

use App\Models\Business;
use App\Models\BusinessVerification;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * When all required KYC items are submitted, call Mevon Rubies business createrubies.
 * If an account number is returned, treat KYC as satisfied: auto-approve pending verifications.
 */
class BusinessRubiesKycAutoVerificationService
{
    public function __construct(
        private MevonRubiesVirtualAccountService $rubies,
    ) {}

    /**
     * @return array{verified: bool, attempted: bool, skipped: bool, message: string}
     */
    public function attemptAfterSubmission(Business $business): array
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

        if (! $this->rubies->isConfigured()) {
            return [
                'verified' => false,
                'attempted' => false,
                'skipped' => true,
                'message' => 'Mevon Rubies is not configured.',
            ];
        }

        if (! $business->hasAllRequiredKycDocumentsSubmittedForAutoVerify()) {
            return [
                'verified' => false,
                'attempted' => false,
                'skipped' => true,
                'message' => '',
            ];
        }

        if (trim((string) $business->cac_registration_number) === '' || $business->rubies_signatory_dob === null) {
            Log::notice('business.rubies_kyc_auto_verify.blocked_missing_cac_or_dob', ['business_id' => $business->id]);

            return [
                'verified' => false,
                'attempted' => true,
                'skipped' => false,
                'message' => 'CAC / RC number and signatory date of birth must be on file (submit a CAC document with those details).',
            ];
        }

        $lock = Cache::lock('business_rubies_kyc_auto_verify:'.$business->id, 45);

        try {
            return $lock->block(15, function () use ($business) {
                $business->refresh();

                if (! empty($business->rubies_business_account_number)) {
                    return [
                        'verified' => $business->hasAllKycDocumentsApproved(),
                        'attempted' => false,
                        'skipped' => true,
                        'message' => '',
                    ];
                }

                if (! $business->hasAllRequiredKycDocumentsSubmittedForAutoVerify()) {
                    return [
                        'verified' => false,
                        'attempted' => false,
                        'skipped' => true,
                        'message' => '',
                    ];
                }

                try {
                    $va = $this->rubies->createRubiesBusinessAccountForBusiness($business);
                } catch (\Throwable $e) {
                    Log::warning('business.rubies_kyc_auto_verify.mevon_failed', [
                        'business_id' => $business->id,
                        'error' => $e->getMessage(),
                    ]);

                    return [
                        'verified' => false,
                        'attempted' => true,
                        'skipped' => false,
                        'message' => $e->getMessage(),
                    ];
                }

                $note = 'Auto-approved: Rubies business pay-in account created (Mevon verification).';

                DB::transaction(function () use ($business, $va, $note) {
                    $locked = Business::query()->whereKey($business->id)->lockForUpdate()->first();
                    if ($locked === null) {
                        return;
                    }
                    if (! empty($locked->rubies_business_account_number)) {
                        return;
                    }

                    $locked->update([
                        'rubies_business_account_number' => $va['account_number'] ?? null,
                        'rubies_business_account_name' => $va['account_name'] ?? null,
                        'rubies_business_bank_name' => $va['bank_name'] ?? null,
                        'rubies_business_bank_code' => $va['bank_code'] ?? null,
                        'rubies_business_reference' => $va['reference'] ?? null,
                        'rubies_business_account_created_at' => now(),
                    ]);

                    $requiredTypes = BusinessVerification::getRequiredTypes();
                    BusinessVerification::query()
                        ->where('business_id', $locked->id)
                        ->whereIn('verification_type', $requiredTypes)
                        ->whereIn('status', [
                            BusinessVerification::STATUS_PENDING,
                            BusinessVerification::STATUS_UNDER_REVIEW,
                        ])
                        ->update([
                            'status' => BusinessVerification::STATUS_APPROVED,
                            'reviewed_at' => now(),
                            'reviewed_by' => null,
                            'admin_notes' => $note,
                        ]);
                });

                $business->refresh();

                Log::info('business.rubies_kyc_auto_verify.completed', [
                    'business_id' => $business->id,
                    'account_suffix' => $business->rubies_business_account_number !== null && $business->rubies_business_account_number !== ''
                        ? substr((string) $business->rubies_business_account_number, -4)
                        : null,
                ]);

                return [
                    'verified' => true,
                    'attempted' => true,
                    'skipped' => false,
                    'message' => '',
                ];
            });
        } catch (LockTimeoutException) {
            Log::notice('business.rubies_kyc_auto_verify.lock_timeout', ['business_id' => $business->id]);

            return [
                'verified' => false,
                'attempted' => true,
                'skipped' => false,
                'message' => 'Verification is processing. Refresh this page in a moment.',
            ];
        }
    }
}
