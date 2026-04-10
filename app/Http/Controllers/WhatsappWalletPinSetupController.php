<?php

namespace App\Http\Controllers;

use App\Services\Whatsapp\WhatsappWalletPinSetupWebService;
use Illuminate\Http\Request;

class WhatsappWalletPinSetupController extends Controller
{
    public function show(string $token, WhatsappWalletPinSetupWebService $pinSetup)
    {
        $meta = $pinSetup->describeToken($token);
        if (! ($meta['ok'] ?? false)) {
            abort(404);
        }

        return view('wallet.whatsapp-pin-setup', [
            'token' => $token,
        ]);
    }

    public function submit(Request $request, string $token, WhatsappWalletPinSetupWebService $pinSetup)
    {
        $request->validate([
            'wallet_pin' => ['required', 'regex:/^\d{4}$/'],
            'wallet_pin_confirmation' => ['required', 'regex:/^\d{4}$/'],
        ]);

        $result = $pinSetup->completeSetup(
            $token,
            (string) $request->input('wallet_pin'),
            (string) $request->input('wallet_pin_confirmation')
        );
        if (! ($result['ok'] ?? false)) {
            return back()->withErrors([
                'wallet_pin' => $result['error'] ?? 'Could not save PIN.',
            ])->withInput();
        }

        return view('wallet.whatsapp-pin-setup-done');
    }
}
