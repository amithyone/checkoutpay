<?php

namespace App\Services\Consumer;

use App\Models\Business;
use App\Models\BusinessAccountApplication;
use App\Models\BusinessWebsite;
use App\Models\WhatsappWallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class BusinessAccountOnboardingWorkflowService
{
    public function __construct(
        private ConsumerBusinessWalletLedgerService $businessLedger,
    ) {}

    /**
     * @param  array{status_label?: string|null, progress_percent?: int|null, rejected_reason?: string|null}  $options
     * @return array{ok: bool, message: string}
     */
    public function updateStatus(BusinessAccountApplication $application, string $status, array $options = []): array
    {
        $allowed = [
            BusinessAccountApplication::STATUS_UNDER_REVIEW,
            BusinessAccountApplication::STATUS_APPROVED,
            BusinessAccountApplication::STATUS_AWAITING_PASSWORD,
            BusinessAccountApplication::STATUS_REJECTED,
        ];

        if (! in_array($status, $allowed, true)) {
            return ['ok' => false, 'message' => 'Invalid status.'];
        }

        if ($application->status === BusinessAccountApplication::STATUS_ACTIVE) {
            return ['ok' => false, 'message' => 'Active applications cannot be changed.'];
        }

        if ($status === BusinessAccountApplication::STATUS_REJECTED) {
            return $this->reject($application, trim((string) ($options['rejected_reason'] ?? 'Application declined.')));
        }

        if (in_array($status, [BusinessAccountApplication::STATUS_APPROVED, BusinessAccountApplication::STATUS_AWAITING_PASSWORD], true)) {
            return $this->approve($application, $options);
        }

        try {
            $application->update([
                'status' => $status,
                'progress_percent' => array_key_exists('progress_percent', $options) && $options['progress_percent'] !== null
                    ? max(0, min(100, (int) $options['progress_percent']))
                    : BusinessAccountApplication::defaultProgressForStatus($status),
                'status_label' => $options['status_label'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('business_account_onboarding.status_update_failed', [
                'application_id' => $application->id,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => 'Could not update application status.'];
        }

        return ['ok' => true, 'message' => 'Application updated.'];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{ok: bool, message: string}
     */
    private function approve(BusinessAccountApplication $application, array $options = []): array
    {
        if (Business::query()->where('email', $application->email)->exists()) {
            return ['ok' => false, 'message' => 'A business with this email already exists.'];
        }

        try {
            DB::transaction(function () use ($application, $options) {
                $row = BusinessAccountApplication::query()->lockForUpdate()->find($application->id);
                if (! $row) {
                    throw new \RuntimeException('application_missing');
                }

                if ($row->linked_business_id) {
                    throw new \RuntimeException('already_provisioned');
                }

                $wallet = WhatsappWallet::query()->lockForUpdate()->find($row->whatsapp_wallet_id);
                if (! $wallet) {
                    throw new \RuntimeException('wallet_missing');
                }

                if ($wallet->linked_business_id && (int) $wallet->linked_business_id !== (int) $row->linked_business_id) {
                    throw new \RuntimeException('wallet_already_linked');
                }

                $categories = (array) ($row->service_categories ?? ['payments']);
                $business = Business::query()->create([
                    'name' => (string) $row->business_name,
                    'email' => (string) $row->email,
                    'password' => Hash::make(Str::random(32)),
                    'phone' => $row->phone,
                    'address' => $row->address,
                    'website' => $row->website_url,
                    'service_categories' => $categories,
                    'is_active' => true,
                    'email_verified_at' => null,
                ]);

                if ($row->website_url) {
                    BusinessWebsite::query()->create([
                        'business_id' => $business->id,
                        'website_url' => (string) $row->website_url,
                        'is_approved' => false,
                    ]);
                }

                if ($wallet->hasBusinessPayIn()) {
                    $business->update([
                        'rubies_business_account_number' => $wallet->business_pay_in_account_number,
                        'rubies_business_account_name' => $wallet->business_pay_in_account_name ?: $row->business_name,
                        'rubies_business_bank_name' => $wallet->business_pay_in_bank_name,
                        'rubies_business_bank_code' => $wallet->business_pay_in_bank_code,
                        'rubies_business_account_created_at' => now(),
                    ]);
                }

                $this->businessLedger->syncBalanceFromLinkedBusiness($wallet, $business);

                $row->update([
                    'linked_business_id' => $business->id,
                    'status' => BusinessAccountApplication::STATUS_AWAITING_PASSWORD,
                    'progress_percent' => BusinessAccountApplication::defaultProgressForStatus(BusinessAccountApplication::STATUS_AWAITING_PASSWORD),
                    'status_label' => $options['status_label'] ?? null,
                    'approved_at' => now(),
                ]);

                $wallet->update(['active_business_account_application_id' => $row->id]);

                try {
                    $business->sendEmailVerificationNotification();
                } catch (\Throwable) {
                    // Non-fatal: user can resend from dashboard notice later.
                }
            });
        } catch (\Throwable $e) {
            Log::error('business_account_onboarding.approve_failed', [
                'application_id' => $application->id,
                'error' => $e->getMessage(),
            ]);

            return match ($e->getMessage()) {
                'application_missing' => ['ok' => false, 'message' => 'Application not found.'],
                'already_provisioned' => ['ok' => false, 'message' => 'Business account already provisioned for this application.'],
                'wallet_missing' => ['ok' => false, 'message' => 'Wallet not found.'],
                'wallet_already_linked' => ['ok' => false, 'message' => 'Wallet is already linked to another business.'],
                default => ['ok' => false, 'message' => 'Could not approve application.'],
            };
        }

        return ['ok' => true, 'message' => 'Business account created. User must set a dashboard password in the app.'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function reject(BusinessAccountApplication $application, string $reason): array
    {
        if ($reason === '') {
            $reason = 'Application declined.';
        }

        try {
            DB::transaction(function () use ($application, $reason) {
                $row = BusinessAccountApplication::query()->lockForUpdate()->find($application->id);
                if (! $row) {
                    throw new \RuntimeException('application_missing');
                }

                $row->update([
                    'status' => BusinessAccountApplication::STATUS_REJECTED,
                    'progress_percent' => 0,
                    'rejected_reason' => $reason,
                    'approved_at' => null,
                ]);

                WhatsappWallet::query()
                    ->where('id', $row->whatsapp_wallet_id)
                    ->where('active_business_account_application_id', $row->id)
                    ->update(['active_business_account_application_id' => null]);
            });
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Could not reject application.'];
        }

        return ['ok' => true, 'message' => 'Application rejected.'];
    }
}
