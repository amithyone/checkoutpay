<?php

namespace App\Http\Controllers;

use App\Services\Whatsapp\WhatsappWalletPartnerPayIntentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsappWalletPartnerPayWebController extends Controller
{
    public function show(string $token, WhatsappWalletPartnerPayIntentService $service): View
    {
        $desc = $service->describeForWeb($token);
        if (! ($desc['ok'] ?? false)) {
            abort(404);
        }

        $d = $desc['data'] ?? [];

        return view('wallet.whatsapp-partner-pay-confirm', [
            'token' => $token,
            'business_name' => (string) ($d['business_name'] ?? ''),
            'amount' => (float) ($d['amount'] ?? 0),
            'order_summary' => (string) ($d['order_summary'] ?? ''),
            'order_reference' => (string) ($d['order_reference'] ?? ''),
            'payer_name' => (string) ($d['payer_name'] ?? ''),
        ]);
    }

    public function submit(Request $request, string $token, WhatsappWalletPartnerPayIntentService $service): RedirectResponse|View
    {
        $request->validate([
            'wallet_pin' => ['required', 'regex:/^\d{4}$/'],
        ]);

        $result = $service->completeWithPin($token, (string) $request->input('wallet_pin'));
        if (! ($result['ok'] ?? false)) {
            return back()->withErrors([
                'wallet_pin' => $result['message'] ?? 'Payment failed.',
            ])->withInput();
        }

        $data = $result['data'] ?? [];

        return view('wallet.whatsapp-partner-pay-done', [
            'amount' => (float) ($data['amount'] ?? 0),
            'transaction_id' => (string) ($data['transaction_id'] ?? ''),
        ]);
    }
}
