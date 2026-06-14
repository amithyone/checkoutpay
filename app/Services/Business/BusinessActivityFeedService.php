<?php

namespace App\Services\Business;

use App\Models\Business;
use App\Models\BusinessTransaction;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

final class BusinessActivityFeedService
{
    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(Business $business, Request $request, int $perPage = 20): LengthAwarePaginator
    {
        $items = $this->collect($business, $request);

        $page = max(1, (int) $request->input('page', 1));
        $total = $items->count();
        $slice = $items->slice(($page - 1) * $perPage, $perPage)->values();

        return new Paginator(
            $slice,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function recent(Business $business, int $limit = 10): Collection
    {
        return $this->collect($business, new Request())->take($limit)->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collect(Business $business, Request $request): Collection
    {
        $paymentRows = $this->paymentRows($business, $request);
        $loanRows = $this->loanRepaymentRows($business, $request);

        return $paymentRows
            ->concat($loanRows)
            ->sortByDesc(fn (array $row) => $row['occurred_at']->getTimestamp())
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function paymentRows(Business $business, Request $request): Collection
    {
        $query = $business->payments()->with('website')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->string('date_from')->toString());
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->string('date_to')->toString());
        }
        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where('transaction_id', 'like', '%'.$search.'%');
        }

        return $query->get()->map(fn (Payment $payment) => [
            'kind' => 'payment',
            'id' => $payment->id,
            'reference' => $payment->transaction_id,
            'amount' => (float) $payment->amount,
            'direction' => 'in',
            'counterparty_label' => $payment->payer_name,
            'description' => null,
            'status' => $payment->status,
            'occurred_at' => $payment->created_at ?? now(),
            'website' => $payment->website,
            'payment' => $payment,
            'loan_transaction' => null,
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function loanRepaymentRows(Business $business, Request $request): Collection
    {
        if ($request->filled('status') && ! in_array($request->string('status')->toString(), ['approved', 'completed'], true)) {
            return collect();
        }

        $query = BusinessTransaction::query()
            ->where('business_id', $business->id)
            ->whereIn('type', [
                BusinessLoanTransactionService::TYPE_REPAYMENT_OUT,
                BusinessLoanTransactionService::TYPE_REPAYMENT_IN,
            ])
            ->with('counterparty')
            ->orderByDesc('transaction_date');

        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->string('date_from')->toString());
        }
        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->string('date_to')->toString());
        }
        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', $search)
                    ->orWhere('description', 'like', $search);
            });
        }

        return $query->get()->map(function (BusinessTransaction $row) {
            $isOut = $row->type === BusinessLoanTransactionService::TYPE_REPAYMENT_OUT;

            return [
                'kind' => 'loan_repayment',
                'id' => $row->id,
                'reference' => $row->reference ?? ('LOAN-TX-'.$row->id),
                'amount' => (float) $row->amount,
                'direction' => $isOut ? 'out' : 'in',
                'counterparty_label' => $row->counterparty?->name,
                'description' => $row->description,
                'status' => 'completed',
                'occurred_at' => $row->transaction_date ?? $row->created_at ?? now(),
                'website' => null,
                'payment' => null,
                'loan_transaction' => $row,
            ];
        });
    }
}
