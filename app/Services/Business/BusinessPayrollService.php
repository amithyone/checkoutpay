<?php

namespace App\Services\Business;

use App\Models\Bank;
use App\Models\Business;
use App\Models\BusinessDisbursementBatch;
use App\Models\BusinessDisbursementItem;
use App\Models\BusinessEmployee;
use App\Models\BusinessSalarySchedule;
use App\Models\MevonPayLedgerEntry;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\Consumer\ConsumerBusinessWalletLedgerService;
use App\Services\Consumer\ConsumerWalletPushNotificationService;
use App\Services\Consumer\ConsumerWalletTransactionScope;
use App\Services\MavonPayTransferService;
use App\Services\MevonPay\MevonPayLedgerRecorder;
use App\Services\NigerianBankCodeNormalizer;
use App\Services\Payout\BankPayoutNarration;
use App\Services\Whatsapp\WhatsappWalletMoneyFormatter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Staff payroll funded from the merchant business account balance (dashboard balance),
 * not personal CheckoutNow wallet balance.
 */
final class BusinessPayrollService
{
    public function __construct(
        private BusinessWhatsappWalletLinkService $walletLink,
        private ConsumerBusinessWalletLedgerService $businessLedger,
        private MavonPayTransferService $mavon,
        private MevonPayLedgerRecorder $ledger,
        private ConsumerWalletPushNotificationService $walletPush,
    ) {}

    public function linkedWallet(Business $business): ?WhatsappWallet
    {
        return $this->walletLink->linkedWallet($business);
    }

    /**
     * @param  list<int>|null  $employeeIds
     * @param  'cycle'|'monthly'  $amountMode  cycle = each staff's pay-frequency slice; monthly = full month
     */
    public function createBulkBatch(
        Business $business,
        ?array $employeeIds = null,
        ?string $notes = null,
        string $amountMode = 'cycle',
    ): array {
        $amountMode = in_array($amountMode, ['cycle', 'monthly'], true) ? $amountMode : 'cycle';

        $query = BusinessEmployee::query()
            ->where('business_id', $business->id)
            ->where('is_active', true);

        if ($employeeIds !== null) {
            if ($employeeIds === []) {
                return ['ok' => false, 'message' => 'No active employees selected.'];
            }
            $query->whereIn('id', $employeeIds);
        }

        $employees = $query->get();
        if ($employees->isEmpty()) {
            return ['ok' => false, 'message' => 'No active employees selected.'];
        }

        $amounts = [];
        $total = 0.0;
        $skippedFullyPaid = 0;
        foreach ($employees as $employee) {
            $requested = $amountMode === 'monthly'
                ? $employee->monthlyAmount()
                : $employee->amountPerPayCycle();
            $amount = round(min(max(0, $requested), $employee->remainingSalaryThisMonth()), 2);
            if ($amount <= 0) {
                $skippedFullyPaid++;
                continue;
            }
            $amounts[$employee->id] = $amount;
            $total += $amount;
        }
        $total = round($total, 2);

        if ($total <= 0) {
            return [
                'ok' => false,
                'message' => $skippedFullyPaid > 0
                    ? 'Selected staff already received their full monthly salary this month.'
                    : 'Total salary amount must be greater than zero.',
            ];
        }

        $available = $this->availableBusinessBalance($business);
        if ($available + 0.0001 < $total) {
            return ['ok' => false, 'message' => 'Insufficient business balance for this payroll run. Available: ₦'.number_format($available, 2)];
        }

        return DB::transaction(function () use ($business, $employees, $total, $notes, $amounts, $amountMode) {
            $batch = BusinessDisbursementBatch::query()->create([
                'business_id' => $business->id,
                'kind' => 'bulk',
                'status' => 'pending',
                'total_amount_ngn' => $total,
                'item_count' => count($amounts),
                'notes' => trim(($notes ?: '').' [amount_mode='.$amountMode.']'),
            ]);

            foreach ($employees as $employee) {
                $amount = $amounts[$employee->id] ?? 0;
                if ($amount <= 0) {
                    continue;
                }
                BusinessDisbursementItem::query()->create([
                    'batch_id' => $batch->id,
                    'business_employee_id' => $employee->id,
                    'recipient_name' => $employee->name,
                    'payment_method' => $employee->payment_method,
                    'phone_e164' => $employee->phone_e164,
                    'bank_code' => $employee->bank_code,
                    'account_number' => $employee->account_number,
                    'amount_ngn' => $amount,
                    'status' => 'pending',
                    'idempotency_key' => 'payroll:'.$batch->id.':emp:'.$employee->id.':'.$amountMode,
                    'due_at' => now(),
                ]);
            }

            return ['ok' => true, 'batch' => $batch->fresh('items'), 'message' => 'Payroll batch created.'];
        });
    }

    public function processBatch(BusinessDisbursementBatch $batch): void
    {
        $batch = $batch->fresh(['items', 'business']);
        if (! in_array($batch->status, ['pending', 'processing', 'partial_failed'], true)) {
            return;
        }

        if (! $batch->business) {
            $batch->update(['status' => 'failed']);

            return;
        }

        $batch->update(['status' => 'processing']);

        $success = 0;
        $failed = 0;

        foreach ($batch->items()->whereIn('status', ['pending', 'failed'])->get() as $item) {
            if ($this->processItem($batch, $item)) {
                $success++;
            } else {
                $failed++;
            }
        }

        $batch->refresh();
        $batch->update([
            'success_count' => $success,
            'failed_count' => $failed,
            'status' => $failed === 0 ? 'completed' : ($success > 0 ? 'partial_failed' : 'failed'),
            'processed_at' => now(),
        ]);
    }

    public function processItem(BusinessDisbursementBatch $batch, BusinessDisbursementItem $item): bool
    {
        if ($item->status === 'completed') {
            return true;
        }

        $item->update(['status' => 'processing']);
        $business = $batch->business;
        if (! $business) {
            $item->update([
                'status' => 'failed',
                'error_message' => 'Business missing.',
                'processed_at' => now(),
            ]);

            return false;
        }

        $employee = $item->employee;
        if ($employee) {
            $remaining = $employee->remainingSalaryThisMonth();
            $requested = round((float) $item->amount_ngn, 2);
            if ($remaining <= 0 || $requested < 1) {
                $item->update([
                    'status' => 'skipped',
                    'error_message' => $remaining <= 0
                        ? 'Monthly salary already fully paid for this month.'
                        : 'Amount too small to pay.',
                    'processed_at' => now(),
                ]);

                return false;
            }
            if ($requested > $remaining + 0.0001) {
                $capped = $remaining;
                if ($capped < 1) {
                    $item->update([
                        'status' => 'skipped',
                        'error_message' => 'Monthly salary already fully paid for this month.',
                        'processed_at' => now(),
                    ]);

                    return false;
                }
                $item->update(['amount_ngn' => $capped]);
                $item->amount_ngn = $capped;
            }
        }

        try {
            if ($item->payment_method === BusinessEmployee::METHOD_WALLET) {
                $result = $this->payToWalletFromBusiness($business, $item);
            } else {
                $result = $this->payToBankFromBusiness($business, $item);
            }

            if ($result['ok'] ?? false) {
                $item->update([
                    'status' => 'completed',
                    'wallet_transaction_id' => $result['transaction_id'] ?? null,
                    'provider_reference' => $result['reference'] ?? null,
                    'processed_at' => now(),
                    'error_message' => null,
                ]);

                $this->notifyPayrollReceived(
                    $business,
                    $item,
                    round((float) $item->amount_ngn, 2),
                    $result['recipient_wallet'] ?? null,
                );

                return true;
            }

            $item->update([
                'status' => 'failed',
                'error_message' => (string) ($result['message'] ?? 'Payment failed'),
                'processed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('business_payroll_item_failed', [
                'batch_id' => $batch->id,
                'item_id' => $item->id,
                'error' => $e->getMessage(),
            ]);
            $item->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'processed_at' => now(),
            ]);
        }

        return false;
    }

    /**
     * @return array{ok: bool, message?: string, transaction_id?: int, reference?: string, recipient_wallet?: WhatsappWallet}
     */
    private function payToWalletFromBusiness(Business $business, BusinessDisbursementItem $item): array
    {
        $recipientPhone = (string) $item->phone_e164;
        if ($recipientPhone === '') {
            return ['ok' => false, 'message' => 'Employee wallet phone missing.'];
        }

        $amount = round((float) $item->amount_ngn, 2);
        if ($amount < 1) {
            return ['ok' => false, 'message' => 'Invalid amount.'];
        }

        $narration = BankPayoutNarration::forPayroll($business, (string) $item->recipient_name);

        return DB::transaction(function () use ($business, $recipientPhone, $amount, $item, $narration) {
            $lockedBusiness = Business::query()->lockForUpdate()->find($business->id);
            if (! $lockedBusiness) {
                return ['ok' => false, 'message' => 'Business not found.'];
            }

            $available = round((float) $lockedBusiness->getAvailableBalance(), 2);
            if ($available + 0.0001 < $amount) {
                return ['ok' => false, 'message' => 'Insufficient business balance.'];
            }

            $recipient = WhatsappWallet::query()
                ->where('phone_e164', $recipientPhone)
                ->where('status', WhatsappWallet::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();

            if (! $recipient) {
                return ['ok' => false, 'message' => 'Recipient CheckoutNow wallet not found.'];
            }

            $lockedBusiness->decrement('balance', $amount);
            $lockedBusiness->refresh();
            $this->syncLinkedWalletCache($lockedBusiness);

            $recipient->balance = round((float) $recipient->balance + $amount, 2);
            $recipient->save();

            $bizName = trim((string) ($lockedBusiness->name ?? 'Business')) ?: 'Business';
            $creditTxn = WhatsappWalletTransaction::query()->create([
                'whatsapp_wallet_id' => $recipient->id,
                'sender_name' => $bizName,
                'type' => WhatsappWalletTransaction::TYPE_P2P_CREDIT,
                'ledger_scope' => ConsumerWalletTransactionScope::SCOPE_PERSONAL,
                'amount' => $amount,
                'balance_after' => (float) $recipient->balance,
                'counterparty_phone_e164' => null,
                'meta' => [
                    'payroll_item_id' => $item->id,
                    'source' => 'business_payroll',
                    'funded_from' => 'business_balance',
                    'business_id' => $lockedBusiness->id,
                    'narration' => $narration,
                    'description' => $narration,
                ],
            ]);

            return [
                'ok' => true,
                'transaction_id' => $creditTxn->id,
                'recipient_wallet' => $recipient->fresh(),
            ];
        });
    }

    /**
     * @return array{ok: bool, message?: string, reference?: string}
     */
    private function payToBankFromBusiness(Business $business, BusinessDisbursementItem $item): array
    {
        $amount = round((float) $item->amount_ngn, 2);
        $accountNumber = preg_replace('/\D+/', '', (string) $item->account_number) ?? '';
        $bankCode = trim((string) $item->bank_code);
        $accountName = trim((string) ($item->recipient_name ?? 'Staff'));

        if ($amount < 1 || strlen($accountNumber) !== 10 || $bankCode === '') {
            return ['ok' => false, 'message' => 'Invalid bank payout details.'];
        }

        if (! $this->mavon->isConfigured()) {
            return ['ok' => false, 'message' => 'Bank payout is not configured. Contact support.'];
        }

        $nip = NigerianBankCodeNormalizer::toNipTransferCode($bankCode);
        $bankName = Bank::query()->where('code', $nip)->value('name')
            ?? Bank::query()->where('code', $bankCode)->value('name')
            ?? 'Bank';

        $narration = BankPayoutNarration::forPayroll($business, $accountName);

        return DB::transaction(function () use ($business, $item, $amount, $accountNumber, $nip, $bankName, $accountName, $narration) {
            $lockedBusiness = Business::query()->lockForUpdate()->find($business->id);
            if (! $lockedBusiness) {
                return ['ok' => false, 'message' => 'Business not found.'];
            }

            $available = round((float) $lockedBusiness->getAvailableBalance(), 2);
            if ($available + 0.0001 < $amount) {
                return ['ok' => false, 'message' => 'Insufficient business balance.'];
            }

            $reference = 'pr_'.$item->id.'_'.Str::lower(Str::random(10));
            $sessionId = 'PR'.$item->id.'_'.now()->format('YmdHis');

            $result = $this->mavon->createTransfer([
                'amount' => $amount,
                'bankCode' => $nip,
                'bankName' => $bankName,
                'creditAccountName' => $accountName,
                'creditAccountNumber' => $accountNumber,
                'narration' => $narration,
                'reference' => $reference,
                'sessionId' => $sessionId,
            ]);

            $bucket = $result['bucket'] ?? MavonPayTransferService::BUCKET_FAILED;

            $this->ledger->recordOutbound(
                MevonPayLedgerEntry::FLOW_BUSINESS_WITHDRAWAL,
                $amount,
                $reference,
                MevonPayLedgerEntry::PAYOUT_API_CREATETRANSFER,
                $bucket,
                (string) config('services.mevonpay.debit_account_number', ''),
                $item,
                [
                    'business_id' => $lockedBusiness->id,
                    'payroll_item_id' => $item->id,
                    'batch_id' => $item->batch_id,
                    'narration' => $narration,
                    'description' => $narration,
                    'response_code' => $result['response_code'] ?? null,
                    'source' => 'business_payroll',
                ],
            );

            if ($bucket !== MavonPayTransferService::BUCKET_SUCCESSFUL) {
                return [
                    'ok' => false,
                    'message' => (string) ($result['response_message'] ?? 'Bank transfer failed.'),
                    'reference' => $reference,
                ];
            }

            $lockedBusiness->decrement('balance', $amount);
            $lockedBusiness->refresh();
            $this->syncLinkedWalletCache($lockedBusiness);

            return ['ok' => true, 'reference' => $reference];
        });
    }

    public function createSchedule(Business $business, array $data): array
    {
        $employeeIds = array_values(array_filter($data['employee_ids'] ?? [], 'is_numeric'));
        $installments = max(1, min(31, (int) ($data['installment_count'] ?? 4)));

        $employees = BusinessEmployee::query()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->when($employeeIds !== [], fn ($q) => $q->whereIn('id', $employeeIds))
            ->get();

        if ($employees->isEmpty()) {
            return ['ok' => false, 'message' => 'Select at least one active employee.'];
        }

        $total = round((float) ($data['total_monthly_amount_ngn'] ?? 0), 2);
        if ($total <= 0) {
            $total = round($employees->sum(fn (BusinessEmployee $e) => (float) $e->monthly_salary_ngn), 2);
        }
        if ($total <= 0) {
            return ['ok' => false, 'message' => 'Enter a valid monthly amount.'];
        }

        $schedule = BusinessSalarySchedule::query()->create([
            'business_id' => $business->id,
            'name' => (string) ($data['name'] ?? 'Salary schedule'),
            'cadence' => (string) ($data['cadence'] ?? 'weekly'),
            'total_monthly_amount_ngn' => $total,
            'installment_count' => $installments,
            'start_date' => $data['start_date'] ?? now()->toDateString(),
            'end_date' => $data['end_date'] ?? null,
            'status' => 'active',
            'employee_ids' => $employeeIds ?: $employees->pluck('id')->all(),
        ]);

        $this->generateScheduleItems($schedule);

        return ['ok' => true, 'schedule' => $schedule->fresh()];
    }

    public function generateScheduleItems(BusinessSalarySchedule $schedule): void
    {
        $business = $schedule->business;
        if (! $business) {
            return;
        }

        $employees = BusinessEmployee::query()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->when(is_array($schedule->employee_ids) && $schedule->employee_ids !== [], fn ($q) => $q->whereIn('id', $schedule->employee_ids))
            ->get();

        if ($employees->isEmpty()) {
            return;
        }

        $monthlyTotal = round($employees->sum(fn (BusinessEmployee $e) => (float) $e->monthly_salary_ngn), 2);
        if ($monthlyTotal <= 0) {
            return;
        }

        $cadenceDays = match ($schedule->cadence) {
            'daily' => 1,
            'biweekly' => 14,
            'monthly' => 30,
            default => 7,
        };

        $batch = BusinessDisbursementBatch::query()->create([
            'business_id' => $business->id,
            'kind' => 'scheduled',
            'status' => 'pending',
            'total_amount_ngn' => $monthlyTotal,
            'item_count' => $employees->count() * (int) $schedule->installment_count,
            'salary_schedule_id' => $schedule->id,
            'notes' => 'Schedule: '.$schedule->name,
        ]);

        $due = $schedule->start_date->copy();
        for ($i = 0; $i < (int) $schedule->installment_count; $i++) {
            foreach ($employees as $employee) {
                $share = round((float) $employee->monthly_salary_ngn / max(1, (int) $schedule->installment_count), 2);
                if ($share <= 0) {
                    continue;
                }
                BusinessDisbursementItem::query()->create([
                    'batch_id' => $batch->id,
                    'business_employee_id' => $employee->id,
                    'recipient_name' => $employee->name,
                    'payment_method' => $employee->payment_method,
                    'phone_e164' => $employee->phone_e164,
                    'bank_code' => $employee->bank_code,
                    'account_number' => $employee->account_number,
                    'amount_ngn' => $share,
                    'status' => 'pending',
                    'idempotency_key' => 'sched:'.$schedule->id.':i:'.$i.':emp:'.$employee->id,
                    'due_at' => $due->copy(),
                ]);
            }
            $due = $due->copy()->addDays($cadenceDays);
        }
    }

    public function runDueItems(): int
    {
        $count = 0;
        $items = BusinessDisbursementItem::query()
            ->where('status', 'pending')
            ->whereNotNull('due_at')
            ->where('due_at', '<=', now())
            ->with(['batch.business', 'employee'])
            ->limit(100)
            ->get();

        foreach ($items as $item) {
            $batch = $item->batch;
            if (! $batch || ! $batch->business) {
                $item->update(['status' => 'skipped', 'error_message' => 'Business missing']);
                continue;
            }
            if ($this->processItem($batch, $item)) {
                $count++;
            }
        }

        return $count;
    }

    public function availableBusinessBalance(Business $business): float
    {
        $business->refresh();

        return round((float) $business->getAvailableBalance(), 2);
    }

    private function notifyPayrollReceived(
        Business $business,
        BusinessDisbursementItem $item,
        float $amount,
        ?WhatsappWallet $creditedWallet = null,
    ): void {
        if ($amount <= 0) {
            return;
        }

        try {
            $wallet = $creditedWallet ?? $this->resolveNotifyWallet($item);
            if (! $wallet) {
                return;
            }

            $bizName = trim((string) ($business->name ?? '')) ?: 'Your employer';
            $amountLabel = WhatsappWalletMoneyFormatter::format($amount, 'NGN');
            $body = $item->payment_method === BusinessEmployee::METHOD_WALLET
                ? sprintf('%s paid you %s salary.', $bizName, $amountLabel)
                : sprintf('%s paid %s salary to your bank.', $bizName, $amountLabel);

            $this->walletPush->notifyMoneyReceived(
                $wallet,
                $amount,
                (float) $wallet->fresh()->balance,
                $body,
                [
                    'credit_source' => 'business_payroll',
                    'sender_name' => $bizName,
                    'currency' => 'NGN',
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('business_payroll_push_failed', [
                'item_id' => $item->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveNotifyWallet(BusinessDisbursementItem $item): ?WhatsappWallet
    {
        $phone = trim((string) $item->phone_e164);
        if ($phone === '' && $item->employee) {
            $phone = trim((string) $item->employee->phone_e164);
        }
        if ($phone !== '') {
            $byPhone = WhatsappWallet::query()
                ->where('phone_e164', $phone)
                ->where('status', WhatsappWallet::STATUS_ACTIVE)
                ->first();
            if ($byPhone) {
                return $byPhone;
            }
        }

        $account = preg_replace('/\D+/', '', (string) $item->account_number) ?? '';
        if (strlen($account) === 10) {
            return WhatsappWallet::query()
                ->where('status', WhatsappWallet::STATUS_ACTIVE)
                ->where(function ($q) use ($account) {
                    $q->where('mevon_virtual_account_number', $account)
                        ->orWhere('business_pay_in_account_number', $account);
                })
                ->first();
        }

        return null;
    }

    private function syncLinkedWalletCache(Business $business): void
    {
        $wallet = $this->linkedWallet($business);
        if ($wallet) {
            $this->businessLedger->refreshLinkedBalanceCache($wallet->fresh());
        }
    }
}
