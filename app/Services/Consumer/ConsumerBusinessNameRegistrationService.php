<?php

namespace App\Services\Consumer;

use App\Models\BusinessNameRegistration;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\Whatsapp\WhatsappWalletMoneyFormatter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class ConsumerBusinessNameRegistrationService
{
    /**
     * @return array{available: bool, fee_amount?: float, fee_currency?: string, fee_label?: string, requirements?: string[], coming_soon_message?: string, estimated_completion_hours_min?: int, estimated_completion_hours_max?: int}
     */
    public function configPayload(): array
    {
        $available = $this->isLive();
        $currency = strtoupper((string) config('consumer_wallet.business_name_registration.fee_currency', 'NGN'));
        $fee = round((float) config('consumer_wallet.business_name_registration.fee_amount', 0), 2);

        if (! $available) {
            return [
                'available' => false,
                'coming_soon_message' => (string) config(
                    'consumer_wallet.business_name_registration.coming_soon_message',
                    'Business name registration coming soon.',
                ),
            ];
        }

        return [
            'available' => true,
            'fee_amount' => $fee,
            'fee_currency' => $currency,
            'fee_label' => WhatsappWalletMoneyFormatter::format($fee, $currency),
            'requirements' => (array) config('consumer_wallet.business_name_registration.requirements', []),
            'estimated_completion_hours_min' => (int) config('consumer_wallet.business_name_registration.estimated_completion_hours_min', 12),
            'estimated_completion_hours_max' => (int) config('consumer_wallet.business_name_registration.estimated_completion_hours_max', 24),
        ];
    }

    /**
     * @return array{config: array<string, mixed>, requests: array<int, array<string, mixed>>, business_account: array<string, mixed>|null}
     */
    public function index(WhatsappWallet $wallet): array
    {
        $wallet = $wallet->fresh();
        $requests = BusinessNameRegistration::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (BusinessNameRegistration $row) => $this->serializeRequest($row))
            ->values()
            ->all();

        return [
            'config' => $this->configPayload(),
            'requests' => $requests,
            'business_account' => $this->businessPayInPayload($wallet),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{ok: bool, message: string, data?: array<string, mixed>, http_status?: int}
     */
    public function submit(WhatsappWallet $wallet, array $input, UploadedFile $idDocument): array
    {
        if (! $this->isLive()) {
            return [
                'ok' => false,
                'message' => (string) config(
                    'consumer_wallet.business_name_registration.coming_soon_message',
                    'Business name registration coming soon.',
                ),
                'http_status' => 403,
            ];
        }

        $fee = round((float) config('consumer_wallet.business_name_registration.fee_amount', 0), 2);
        $currency = strtoupper((string) config('consumer_wallet.business_name_registration.fee_currency', 'NGN'));
        if ($fee <= 0) {
            return [
                'ok' => false,
                'message' => 'Business name registration coming soon.',
                'http_status' => 403,
            ];
        }

        $proposedName = trim((string) ($input['proposed_name'] ?? ''));
        $alternateName = trim((string) ($input['alternate_name'] ?? ''));
        $ownerFullName = trim((string) ($input['owner_full_name'] ?? ''));
        $ownerPhone = trim((string) ($input['owner_phone'] ?? ''));
        $ownerEmail = strtolower(trim((string) ($input['owner_email'] ?? '')));
        $businessAddress = trim((string) ($input['business_address'] ?? ''));
        $nature = trim((string) ($input['nature_of_business'] ?? ''));
        $idType = strtolower(trim((string) ($input['id_type'] ?? '')));

        if (strlen($proposedName) < 3) {
            return ['ok' => false, 'message' => 'Proposed business name must be at least 3 characters.', 'http_status' => 422];
        }
        if ($ownerFullName === '' || $ownerPhone === '' || $businessAddress === '' || $nature === '') {
            return ['ok' => false, 'message' => 'Please complete all required fields.', 'http_status' => 422];
        }
        if (! filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Enter a valid email address.', 'http_status' => 422];
        }
        if (! in_array($idType, [
            BusinessNameRegistration::ID_TYPE_NIN,
            BusinessNameRegistration::ID_TYPE_PASSPORT,
            BusinessNameRegistration::ID_TYPE_DRIVERS_LICENSE,
        ], true)) {
            return ['ok' => false, 'message' => 'Select a valid ID type.', 'http_status' => 422];
        }

        $hoursMin = (int) config('consumer_wallet.business_name_registration.estimated_completion_hours_min', 12);
        $hoursMax = (int) config('consumer_wallet.business_name_registration.estimated_completion_hours_max', 24);
        $reference = $this->nextReference();
        $publicId = 'bnr_'.Str::lower((string) Str::ulid());
        $idPath = null;
        $registration = null;

        try {
            DB::transaction(function () use (
                $wallet,
                $fee,
                $currency,
                $reference,
                $publicId,
                $proposedName,
                $alternateName,
                $ownerFullName,
                $ownerPhone,
                $ownerEmail,
                $businessAddress,
                $nature,
                $idType,
                $hoursMin,
                $hoursMax,
                $idDocument,
                &$idPath,
                &$registration,
            ) {
                $w = WhatsappWallet::query()->lockForUpdate()->find($wallet->id);
                if (! $w) {
                    throw new \RuntimeException('wallet_missing');
                }

                $w->resetDailyTransferIfNeeded();
                $check = $w->canDebit($fee);
                if (! ($check['ok'] ?? false)) {
                    throw new \RuntimeException('insufficient_balance');
                }

                $newBal = round((float) $w->balance - $fee, 2);
                $w->balance = $newBal;
                $w->daily_transfer_total = round((float) $w->daily_transfer_total + $fee, 2);
                $w->daily_transfer_for_date = now()->toDateString();
                $w->save();

                $registration = BusinessNameRegistration::query()->create([
                    'public_id' => $publicId,
                    'whatsapp_wallet_id' => $w->id,
                    'reference' => $reference,
                    'proposed_name' => $proposedName,
                    'alternate_name' => $alternateName !== '' ? $alternateName : null,
                    'owner_full_name' => $ownerFullName,
                    'owner_phone' => $ownerPhone,
                    'owner_email' => $ownerEmail,
                    'business_address' => $businessAddress,
                    'nature_of_business' => $nature,
                    'id_type' => $idType,
                    'id_document_path' => '',
                    'status' => BusinessNameRegistration::STATUS_PAID,
                    'progress_percent' => BusinessNameRegistration::defaultProgressForStatus(BusinessNameRegistration::STATUS_PAID),
                    'fee_amount' => $fee,
                    'fee_currency' => $currency,
                    'submitted_at' => now(),
                    'estimated_completion_hours_min' => $hoursMin,
                    'estimated_completion_hours_max' => $hoursMax,
                ]);

                $idPath = $idDocument->store(
                    'business-name-registrations/'.$w->id.'/'.$registration->id,
                    'local',
                );

                $txn = WhatsappWalletTransaction::query()->create([
                    'whatsapp_wallet_id' => $w->id,
                    'sender_name' => $w->normalizedSenderName(),
                    'type' => WhatsappWalletTransaction::TYPE_BUSINESS_NAME_REGISTRATION_FEE,
                    'amount' => -$fee,
                    'balance_after' => $newBal,
                    'external_reference' => $reference,
                    'meta' => [
                        'channel' => 'consumer_api',
                        'business_name_registration_id' => $registration->id,
                        'business_name_registration_public_id' => $publicId,
                        'proposed_name' => $proposedName,
                    ],
                ]);

                $registration->update([
                    'id_document_path' => $idPath,
                    'fee_transaction_id' => $txn->id,
                ]);
            });
        } catch (\Throwable $e) {
            if ($idPath !== null) {
                Storage::disk('local')->delete($idPath);
            }

            if ($e->getMessage() === 'insufficient_balance') {
                return ['ok' => false, 'message' => 'Insufficient wallet balance', 'http_status' => 422];
            }
            if ($e->getMessage() === 'wallet_missing') {
                return ['ok' => false, 'message' => 'Wallet not found.', 'http_status' => 404];
            }

            Log::error('consumer.business_name_registration.submit_failed', [
                'wallet_id' => $wallet->id,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => 'Could not submit business name registration.', 'http_status' => 422];
        }

        /** @var BusinessNameRegistration $registration */
        return [
            'ok' => true,
            'message' => 'Application submitted.',
            'data' => [
                'reference' => $registration->reference,
                'status' => $registration->status,
                'proposed_name' => $registration->proposed_name,
                'progress_percent' => (int) $registration->progress_percent,
                'fee_amount' => (float) $registration->fee_amount,
                'fee_currency' => (string) $registration->fee_currency,
                'estimated_completion_hours_min' => (int) $registration->estimated_completion_hours_min,
                'estimated_completion_hours_max' => (int) $registration->estimated_completion_hours_max,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function businessPayInPayload(WhatsappWallet $wallet): ?array
    {
        $acct = trim((string) $wallet->business_pay_in_account_number);
        if ($acct === '') {
            return null;
        }

        return [
            'kind' => 'permanent',
            'account_number' => $acct,
            'account_name' => trim((string) ($wallet->business_pay_in_account_name ?? '')) !== ''
                ? (string) $wallet->business_pay_in_account_name
                : null,
            'business_name' => trim((string) ($wallet->business_pay_in_account_name ?? '')) !== ''
                ? (string) $wallet->business_pay_in_account_name
                : null,
            'bank_name' => trim((string) ($wallet->business_pay_in_bank_name ?? '')) !== ''
                ? (string) $wallet->business_pay_in_bank_name
                : null,
            'bank_code' => trim((string) ($wallet->business_pay_in_bank_code ?? '')) !== ''
                ? (string) $wallet->business_pay_in_bank_code
                : null,
            'expires_at' => null,
            'source' => 'business_name_registration',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeRequest(BusinessNameRegistration $row): array
    {
        $payload = [
            'id' => (string) $row->public_id,
            'reference' => (string) $row->reference,
            'proposed_name' => (string) $row->proposed_name,
            'alternate_name' => $row->alternate_name,
            'status' => (string) $row->status,
            'progress_percent' => (int) $row->progress_percent,
            'status_label' => $row->status_label,
            'submitted_at' => $row->submitted_at?->toIso8601String(),
            'estimated_completion_hours_min' => $row->estimated_completion_hours_min,
            'estimated_completion_hours_max' => $row->estimated_completion_hours_max,
            'approved_at' => $row->approved_at?->toIso8601String(),
            'rejected_reason' => $row->rejected_reason,
            'fee_amount' => $row->fee_amount !== null ? (float) $row->fee_amount : null,
            'fee_currency' => $row->fee_currency,
        ];

        $businessAccount = $this->registrationBusinessAccountPayload($row);
        if ($businessAccount !== null) {
            $payload['business_account'] = $businessAccount;
        }

        return $payload;
    }

    public function isLive(): bool
    {
        if (! (bool) config('consumer_wallet.business_name_registration.enabled', false)) {
            return false;
        }

        $fee = round((float) config('consumer_wallet.business_name_registration.fee_amount', 0), 2);

        return $fee > 0;
    }

    private function nextReference(): string
    {
        $year = now()->format('Y');
        $latest = BusinessNameRegistration::query()
            ->where('reference', 'like', 'BNR-'.$year.'-%')
            ->orderByDesc('reference')
            ->value('reference');

        $seq = 1;
        if (is_string($latest) && preg_match('/^BNR-\d{4}-(\d+)$/', $latest, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return sprintf('BNR-%s-%05d', $year, $seq);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function registrationBusinessAccountPayload(BusinessNameRegistration $row): ?array
    {
        $acct = trim((string) ($row->business_account_number ?? ''));
        if ($acct === '') {
            return null;
        }

        return [
            'kind' => 'permanent',
            'account_number' => $acct,
            'account_name' => trim((string) ($row->business_account_name ?? '')) !== ''
                ? (string) $row->business_account_name
                : null,
            'bank_name' => trim((string) ($row->business_bank_name ?? '')) !== ''
                ? (string) $row->business_bank_name
                : null,
            'bank_code' => trim((string) ($row->business_bank_code ?? '')) !== ''
                ? (string) $row->business_bank_code
                : null,
            'expires_at' => null,
        ];
    }
}
