<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\PaymentLink;
use App\Services\PaymentLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PaymentLinkController extends Controller
{
    public function __construct(
        protected PaymentLinkService $links
    ) {}

    protected function currentBusiness(): Business
    {
        $business = Auth::guard('business')->user();
        if (! $business) {
            abort(403);
        }

        return $business;
    }

    protected function authorizeLink(PaymentLink $paymentLink): Business
    {
        if (Auth::guard('admin')->check()) {
            $paymentLink->loadMissing('business');

            return $paymentLink->business;
        }

        $business = $this->currentBusiness();
        if ((int) $paymentLink->business_id !== (int) $business->id) {
            abort(403, 'You do not have permission to manage this payment link.');
        }

        return $business;
    }

    public function index(Request $request): View
    {
        $business = $this->currentBusiness();

        $query = PaymentLink::query()->where('business_id', $business->id)->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('reuse_mode')) {
            $query->where('reuse_mode', $request->reuse_mode);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $links = $query->paginate(20);

        $stats = [
            'total' => PaymentLink::where('business_id', $business->id)->count(),
            'active' => PaymentLink::where('business_id', $business->id)->where('status', PaymentLink::STATUS_ACTIVE)->count(),
            'paid' => PaymentLink::where('business_id', $business->id)->where('status', PaymentLink::STATUS_PAID)->count(),
            'collected_amount' => PaymentLink::where('business_id', $business->id)->sum('collected_amount'),
        ];

        return view('business.payment-links.index', compact('links', 'stats'));
    }

    public function create(): View
    {
        $business = $this->currentBusiness();

        return view('business.payment-links.create', compact('business'));
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->currentBusiness();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'amount_mode' => 'required|in:fixed,open',
            'amount' => 'nullable|required_if:amount_mode,fixed|numeric|min:0.01',
            'reuse_mode' => 'required|in:one_time,reusable',
        ]);

        $link = $this->links->create($business, [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'amount' => $validated['amount_mode'] === 'fixed' ? (float) $validated['amount'] : null,
            'currency' => 'NGN',
            'reuse_mode' => $validated['reuse_mode'],
        ]);

        return redirect()
            ->route('business.payment-links.show', $link)
            ->with('success', 'Payment link created. Share it with your client.');
    }

    public function show(PaymentLink $payment_link): View
    {
        $this->authorizeLink($payment_link);
        $payment_link->load(['linkPayments.payment']);

        return view('business.payment-links.show', ['link' => $payment_link]);
    }

    public function destroy(PaymentLink $payment_link): RedirectResponse
    {
        $this->authorizeLink($payment_link);

        if ((int) $payment_link->collected_count > 0) {
            $payment_link->update(['status' => PaymentLink::STATUS_CANCELLED]);

            return redirect()
                ->route('business.payment-links.index')
                ->with('success', 'Payment link cancelled. Existing payments are unchanged.');
        }

        $payment_link->delete();

        return redirect()
            ->route('business.payment-links.index')
            ->with('success', 'Payment link deleted.');
    }

    public function pause(PaymentLink $payment_link): RedirectResponse
    {
        $this->authorizeLink($payment_link);
        $this->links->pause($payment_link);

        return back()->with('success', 'Payment link paused.');
    }

    public function resume(PaymentLink $payment_link): RedirectResponse
    {
        $this->authorizeLink($payment_link);
        $this->links->resume($payment_link);

        return back()->with('success', 'Payment link is active again.');
    }
}
