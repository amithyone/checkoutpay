<?php

namespace App\Services\Consumer;

use App\Models\BusinessNameRegistration;
use App\Models\WhatsappWallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Admin / partner workflow for business name registration status updates.
 */
final class BusinessNameRegistrationWorkflowService
{
    /**
     * @param  array{status_label?: string|null, progress_percent?: int|null, rejected_reason?: string|null, approved_business_name?: string|null, business_account_number?: string|null, business_account_name?: string|null, business_bank_name?: string|null, business_bank_code?: string|null}  $options
     * @return array{ok: bool, message: string}
     */
    public function updateStatus(BusinessNameRegistration $registration, string $status, array $options = []): array
    {
        $allowed = [
            BusinessNameRegistration::STATUS_PAID,
            BusinessNameRegistration::STATUS_PROCESSING,
            BusinessNameRegistration::STATUS_UNDER_REVIEW,
            BusinessNameRegistration::STATUS_APPROVED,
            BusinessNameRegistration::STATUS_REJECTED,
        ];

        if (! in_array($status, $allowed, true)) {
            return ['ok' => false, 'message' => 'Invalid status.'];
        }

        if ($registration->status === BusinessNameRegistration::STATUS_APPROVED && $status !== BusinessNameRegistration::STATUS_APPROVED) {
            return ['ok' => false, 'message' => 'Approved registrations cannot be moved to another status.'];
        }

        try {
            DB::transaction(function () use ($registration, $status, $options) {
                $row = BusinessNameRegistration::query()->lockForUpdate()->find($registration->id);
                if (! $row) {
                    throw new \RuntimeException('registration_missing');
                }

                $progress = array_key_exists('progress_percent', $options) && $options['progress_percent'] !== null
                    ? max(0, min(100, (int) $options['progress_percent']))
                    : BusinessNameRegistration::defaultProgressForStatus($status);

                $updates = [
                    'status' => $status,
                    'progress_percent' => $progress,
                ];

                if (array_key_exists('status_label', $options)) {
                    $updates['status_label'] = $options['status_label'];
                }

                if ($status === BusinessNameRegistration::STATUS_REJECTED) {
                    $updates['rejected_reason'] = trim((string) ($options['rejected_reason'] ?? 'Application declined.'));
                    if ($updates['rejected_reason'] === '') {
                        $updates['rejected_reason'] = 'Application declined.';
                    }
                    $updates['approved_at'] = null;
                }

                if ($status === BusinessNameRegistration::STATUS_APPROVED) {
                    $approvedName = trim((string) ($options['approved_business_name'] ?? $row->proposed_name));
                    $acct = trim((string) ($options['business_account_number'] ?? ''));
                    $acctName = trim((string) ($options['business_account_name'] ?? $approvedName));
                    $bankName = trim((string) ($options['business_bank_name'] ?? 'Rubies MFB'));
                    $bankCode = trim((string) ($options['business_bank_code'] ?? ''));

                    if ($acct === '') {
                        throw new \RuntimeException('business_va_required');
                    }

                    $updates['approved_at'] = now();
                    $updates['approved_business_name'] = $approvedName;
                    $updates['business_account_number'] = $acct;
                    $updates['business_account_name'] = $acctName !== '' ? $acctName : $approvedName;
                    $updates['business_bank_name'] = $bankName !== '' ? $bankName : 'Rubies MFB';
                    $updates['business_bank_code'] = $bankCode !== '' ? $bankCode : null;
                    $updates['rejected_reason'] = null;

                    $wallet = WhatsappWallet::query()->lockForUpdate()->find($row->whatsapp_wallet_id);
                    if (! $wallet) {
                        throw new \RuntimeException('wallet_missing');
                    }

                    $wallet->update([
                        'business_pay_in_account_number' => $acct,
                        'business_pay_in_account_name' => $updates['business_account_name'],
                        'business_pay_in_bank_name' => $updates['business_bank_name'],
                        'business_pay_in_bank_code' => $updates['business_bank_code'],
                        'active_business_name_registration_id' => $row->id,
                    ]);
                }

                $row->update($updates);
            });
        } catch (\Throwable $e) {
            if ($e->getMessage() === 'business_va_required') {
                return ['ok' => false, 'message' => 'Business account number is required when approving.'];
            }
            if ($e->getMessage() === 'registration_missing' || $e->getMessage() === 'wallet_missing') {
                return ['ok' => false, 'message' => 'Registration or wallet not found.'];
            }

            Log::error('business_name_registration.status_update_failed', [
                'registration_id' => $registration->id,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => 'Could not update registration status.'];
        }

        return ['ok' => true, 'message' => 'Registration updated.'];
    }
}
