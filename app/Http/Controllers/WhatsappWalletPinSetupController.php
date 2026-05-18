<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesWhatsappWalletWebPinSubmit;
use App\Services\Whatsapp\WhatsappWalletPinSetupWebService;
use Illuminate\Http\Request;

class WhatsappWalletPinSetupController extends Controller
{
    use HandlesWhatsappWalletWebPinSubmit;
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
            $error = (string) ($result['error'] ?? 'Could not save PIN.');
            if ($this->isConsumedWhatsappWalletWebLinkError($error)) {
                return view('wallet.whatsapp-pin-setup-done');
            }

            return back()->withErrors([
                'wallet_pin' => $error,
            ])->withInput();
        }

        return view('wallet.whatsapp-pin-setup-done');
    }
}
