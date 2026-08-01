<?php

namespace App\Services\Business;

use App\Models\Business;
use App\Models\BusinessDisbursementBatch;
use App\Models\BusinessDisbursementItem;
use App\Models\BusinessEmployee;
use App\Models\BusinessSalarySchedule;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\Consumer\ConsumerBusinessWalletLedgerService;
use App\Services\Consumer\ConsumerWalletTransactionScope;
use App\Services\Consumer\ConsumerWalletTransferService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class BusinessPayrollService
{
    public function __construct(
        private ConsumerWalletTransferService $transfers,
        private ConsumerBusinessWalletLedgerService $businessLedger,
        private BusinessWhatsappWalletLinkService $walletLink,
    ) {}

    public function linkedWallet(Business $business): ?WhatsappWallet
    {
        return $this->walletLink->linkedWallet($business);
    }

    /**
     * @param  list<int>|null  $employeeIds
     */
    public function createBulkBatch(Business $business, ?array $employeeIds = null, ?string $notes = null): array
    {
        $wallet = $this->linkedWallet($business);
        if (! $wallet) {
            return ['ok' => false, 'message' => 'Link a CheckoutNow wallet before paying staff.'];
        }

        $query = BusinessEmployee::query()
            ->where('business_id', $business->id)
            ->where('is_active', true);

        if ($employeeIds !== null && $employeeIds !== []) {
            $query->whereIn('id', $employeeIds);
        }

        $employees = $query->get();
        if ($employees->isEmpty()) {
            return ['ok' => false, 'message' => 'No active employees selected.'];
        }

        $total = round($employees->sum(fn (BusinessEmployee $e) => (float) $e->monthly_salary_ngn), 2);
        if ($total <= 0) {
            return ['ok' => false, 'message' => 'Total salary amount must be greater than zero.'];
        }

        $available = $this->availableBusinessBalance($business, $wallet);
        if ($available + 0.0001 < $total) {
            return ['ok' => false, 'message' => 'Insufficient business balance for this payroll run.'];
        }

        return DB::transaction(function () use ($business, $employees, $total, $notes) {
            $batch = BusinessDisbursementBatch::query()->create([
                'business_id' => $business->id,
                'kind' => 'bulk',
                'status' => 'pending',
                'total_amount_ngn' => $total,
                'item_count' => $employees->count(),
                'notes' => $notes,
            ]);

            foreach ($employees as $employee) {
                BusinessDisbursementItem::query()->create([
                    'batch_id' => $batch->id,
                    'business_employee_id' => $employee->id,
                    'recipient_name' => $employee->name,
                    'payment_method' => $employee->payment_method,
                    'phone_e164' => $employee->phone_e164,
                    'bank_code' => $employee->bank_code,
                    'account_number' => $employee->account_number,
                    'amount_ngn' => $employee->monthly_salary_ngn,
                    'status' => 'pending',
                    'idempotency_key' => 'payroll:'.$batch->id.':emp:'.$employee->id,
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

        $business = $batch->business;
        $wallet = $this->linkedWallet($business);
        if (! $wallet) {
            $batch->update(['status' => 'failed']);
            return;
        }

        $batch->update(['status' => 'processing']);

        $success = 0;
        $failed = 0;

        foreach ($batch->items()->whereIn('status', ['pending', 'failed'])->get() as $item) {
            $result = $this->processItem($batch, $item, $wallet);
            if ($result) {
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

    public function processItem(BusinessDisbursementBatch $batch, BusinessDisbursementItem $item, WhatsappWallet $wallet): bool
    {
        if ($item->status === 'completed') {
            return true;
        }

        $item->update(['status' => 'processing']);

        try {
            if ($item->payment_method === BusinessEmployee::METHOD_WALLET) {
                $result = $this->payWallet($wallet, $item);
            } else {
                $result = $this->transfers->bankTransfer(
                    $wallet,
                    (float) $item->amount_ngn,
                    (string) $item->account_number,
                    (string) $item->bank_code,
                    'Bank',
                    (string) ($item->recipient_name ?? 'Staff'),
                    'Salary payment',
                    ConsumerWalletTransactionScope::SCOPE_BUSINESS,
                );
            }

            if ($result['ok'] ?? false) {
                $item->update([
                    'status' => 'completed',
                    'wallet_transaction_id' => $result['data']['transaction_id'] ?? $result['transaction_id'] ?? null,
                    'provider_reference' => $result['data']['reference'] ?? $result['reference'] ?? null,
                    'processed_at' => now(),
                    'error_message' => null,
                ]);

                return true;
            }

            $item->update([
                'status' => 'failed',
                'error_message' => (string) ($result['message'] ?? 'Payment failed'),
                'processed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $item->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'processed_at' => now(),
            ]);
        }

        return false;
    }

    /**
     * @return array{ok: bool, message?: string, transaction_id?: int}
     */
    private function payWallet(WhatsappWallet $senderWallet, BusinessDisbursementItem $item): array
    {
        $recipientPhone = (string) $item->phone_e164;
        if ($recipientPhone === '') {
            return ['ok' => false, 'message' => 'Employee wallet phone missing.'];
        }

        $amount = (float) $item->amount_ngn;
        if ($amount < 1) {
            return ['ok' => false, 'message' => 'Invalid amount.'];
        }

        return DB::transaction(function () use ($senderWallet, $recipientPhone, $amount, $item) {
            $sender = WhatsappWallet::query()->lockForUpdate()->find($senderWallet->id);
            if (! $sender) {
                return ['ok' => false, 'message' => 'Sender wallet not found.'];
            }

            $debit = $this->businessLedger->debitLockedWallet($sender, $amount);
            if (! ($debit['ok'] ?? false)) {
                return ['ok' => false, 'message' => (string) ($debit['message'] ?? 'Insufficient business balance.')];
            }

            $recipient = WhatsappWallet::query()
                ->where('phone_e164', $recipientPhone)
                ->where('status', WhatsappWallet::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();

            if (! $recipient) {
                return ['ok' => false, 'message' => 'Recipient wallet not found.'];
            }

            $recipient->balance = round((float) $recipient->balance + $amount, 2);
            $recipient->save();

            $debitTxn = WhatsappWalletTransaction::query()->create([
                'whatsapp_wallet_id' => $sender->id,
                'sender_name' => $this->businessLedger->resolveLedgerSenderName($sender, ConsumerWalletTransactionScope::SCOPE_BUSINESS),
                'type' => WhatsappWalletTransaction::TYPE_P2P_DEBIT,
                'ledger_scope' => ConsumerWalletTransactionScope::SCOPE_BUSINESS,
                'amount' => $amount,
                'balance_after' => (float) ($debit['balance_after'] ?? 0),
                'counterparty_phone_e164' => $recipientPhone,
                'meta' => ['payroll_item_id' => $item->id, 'source' => 'business_payroll'],
            ]);

            WhatsappWalletTransaction::query()->create([
                'whatsapp_wallet_id' => $recipient->id,
                'sender_name' => $item->recipient_name,
                'type' => WhatsappWalletTransaction::TYPE_P2P_CREDIT,
                'ledger_scope' => ConsumerWalletTransactionScope::SCOPE_PERSONAL,
                'amount' => $amount,
                'balance_after' => (float) $recipient->balance,
                'counterparty_phone_e164' => (string) $sender->phone_e164,
                'meta' => ['payroll_item_id' => $item->id, 'source' => 'business_payroll'],
            ]);

            return ['ok' => true, 'transaction_id' => $debitTxn->id];
        });
    }

    public function createSchedule(Business $business, array $data): array
    {
        $employeeIds = array_values(array_filter($data['employee_ids'] ?? [], 'is_numeric'));
        $installments = max(1, min(12, (int) ($data['installment_count'] ?? 4)));
        $total = round((float) ($data['total_monthly_amount_ngn'] ?? 0), 2);

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
            'employee_ids' => $employeeIds ?: null,
        ]);

        $this->generateScheduleItems($schedule);

        return ['ok' => true, 'schedule' => $schedule->fresh()];
    }

    public function generateScheduleItems(BusinessSalarySchedule $schedule): void
    {
        $business = $schedule->business;
        $wallet = $this->linkedWallet($business);
        if (! $wallet) {
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

        $perInstallment = round($monthlyTotal / max(1, (int) $schedule->installment_count), 2);
        $cadenceDays = match ($schedule->cadence) {
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
            ->with(['batch.business'])
            ->limit(100)
            ->get();

        foreach ($items as $item) {
            $batch = $item->batch;
            if (! $batch) {
                continue;
            }
            $wallet = $this->linkedWallet($batch->business);
            if (! $wallet) {
                $item->update(['status' => 'skipped', 'error_message' => 'No linked wallet']);
                continue;
            }
            if ($this->processItem($batch, $item, $wallet)) {
                $count++;
            }
        }

        return $count;
    }

    private function availableBusinessBalance(Business $business, WhatsappWallet $wallet): float
    {
        $business->refresh();

        return $business->getAvailableBalance();
    }
}
