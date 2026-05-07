<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\BusinessLendingOffer;
use Illuminate\View\View;

class PeerLoansController extends Controller
{
    public function index(): View
    {
        $offers = BusinessLendingOffer::with('lender')
            ->publiclyListed()
            ->latest()
            ->paginate(20);

        return view('public.peer-loans.index', compact('offers'));
    }

    public function show(string $slug): View
    {
        $offer = BusinessLendingOffer::with('lender')
            ->where('public_slug', $slug)
            ->publiclyListed()
            ->firstOrFail();

        return view('public.peer-loans.show', compact('offer'));
    }
}
