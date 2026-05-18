<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesWhatsappWalletWebPinSubmit;
use App\Services\Whatsapp\WhatsappWalletSecureTransferAuthService;
use Illuminate\Http\Request;

class WhatsappWalletTransferConfirmController extends Controller
{
    use HandlesWhatsappWalletWebPinSubmit;
    public function show(string $token, WhatsappWalletSecureTransferAuthService $auth)
    {
        $meta = $auth->describePendingWebConfirm($token);
        if (! ($meta['ok'] ?? false)) {
            abort(404);
        }

        return view('wallet.whatsapp-transfer-confirm', [
            'token' => $token,
            'summary' => $meta['summary'] ?? '',
            'kind' => $meta['kind'] ?? '',
        ]);
    }

    public function submit(Request $request, string $token, WhatsappWalletSecureTransferAuthService $auth)
    {
        $request->validate([
            'wallet_pin' => ['required', 'regex:/^\d{4}$/'],
        ]);

        $result = $auth->confirmViaWebPin($token, (string) $request->input('wallet_pin'));
        if (! ($result['ok'] ?? false)) {
            $error = (string) ($result['error'] ?? 'Could not confirm.');
            if ($this->isConsumedWhatsappWalletWebLinkError($error)) {
                return view('wallet.whatsapp-transfer-confirm-done');
            }

            return back()->withErrors(['wallet_pin' => $error])->withInput();
        }

        return view('wallet.whatsapp-transfer-confirm-done');
    }
}
