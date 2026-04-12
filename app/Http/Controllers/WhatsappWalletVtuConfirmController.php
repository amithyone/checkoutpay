<?php

namespace App\Http\Controllers;

use App\Services\Whatsapp\WhatsappWalletVtuWebPinService;
use Illuminate\Http\Request;

class WhatsappWalletVtuConfirmController extends Controller
{
    public function show(string $token, WhatsappWalletVtuWebPinService $vtuPin)
    {
        $meta = $vtuPin->describePending($token);
        if (! ($meta['ok'] ?? false)) {
            abort(404);
        }

        return view('wallet.whatsapp-vtu-confirm', [
            'token' => $token,
            'summary' => $meta['summary'] ?? '',
        ]);
    }

    public function submit(Request $request, string $token, WhatsappWalletVtuWebPinService $vtuPin)
    {
        $request->validate([
            'wallet_pin' => ['required', 'regex:/^\d{4}$/'],
        ]);

        $result = $vtuPin->confirmViaWebPin($token, (string) $request->input('wallet_pin'));
        if (! ($result['ok'] ?? false)) {
            return back()->withErrors(['wallet_pin' => $result['error'] ?? 'Could not confirm.'])->withInput();
        }

        return view('wallet.whatsapp-vtu-confirm-done');
    }
}
