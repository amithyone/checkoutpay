<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Services\Business\BusinessWhatsappWalletLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WhatsappWalletController extends Controller
{
    public function __construct(
        private BusinessWhatsappWalletLinkService $links,
    ) {}

    public function link(Request $request): RedirectResponse
    {
        $business = Auth::guard('business')->user();

        $validated = $request->validate([
            'wallet_phone' => 'required|string|min:10|max:20',
            'wallet_pin' => ['required', 'regex:/^\d{4}$/'],
        ]);

        $result = $this->links->link(
            $business,
            (string) $validated['wallet_phone'],
            (string) $validated['wallet_pin'],
        );

        if (! $result['ok']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', $result['message']);
    }

    public function unlink(Request $request): RedirectResponse
    {
        $business = Auth::guard('business')->user();
        $result = $this->links->unlink($business);

        if (! $result['ok']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', $result['message']);
    }
}
