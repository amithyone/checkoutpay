<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\PaymentLink;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentLinkController extends Controller
{
    public function index(Request $request): View
    {
        $query = PaymentLink::query()->with('business')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('reuse_mode')) {
            $query->where('reuse_mode', $request->reuse_mode);
        }
        if ($request->filled('business_id')) {
            $query->where('business_id', $request->business_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhereHas('business', fn ($bq) => $bq->where('name', 'like', "%{$search}%"));
            });
        }

        $links = $query->paginate(30);
        $businesses = Business::orderBy('name')->get(['id', 'name']);

        $stats = [
            'total' => PaymentLink::count(),
            'active' => PaymentLink::where('status', PaymentLink::STATUS_ACTIVE)->count(),
            'paid' => PaymentLink::where('status', PaymentLink::STATUS_PAID)->count(),
            'collected_amount' => PaymentLink::sum('collected_amount'),
        ];

        return view('admin.payment-links.index', compact('links', 'businesses', 'stats'));
    }

    public function show(PaymentLink $payment_link): View
    {
        $payment_link->load(['business', 'linkPayments.payment']);

        return view('admin.payment-links.show', ['link' => $payment_link]);
    }
}
