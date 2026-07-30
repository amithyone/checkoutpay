<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Services\Broadcast\BroadcastTerminalProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PayAtShopController extends Controller
{
    public function __construct(
        private readonly BroadcastTerminalProvisioner $provisioner,
    ) {}

    public function index(): View
    {
        $business = Auth::guard('business')->user();
        if ($this->provisioner->findForBusiness($business)) {
            $this->provisioner->syncSettlementAccount($business);
        }
        $terminal = $this->provisioner->findForBusiness($business);
        $settlement = $this->provisioner->resolveSettlementAccount($business);
        $canEnable = $business->broadcast_pay_at_shop_enabled && $settlement !== null;
        $revealedSigningKey = session('broadcast_signing_key');

        return view('business.pay-at-shop.index', compact(
            'business',
            'terminal',
            'settlement',
            'canEnable',
            'revealedSigningKey',
        ));
    }

    public function toggle(Request $request): RedirectResponse
    {
        $business = Auth::guard('business')->user();

        if (! $business->broadcast_pay_at_shop_enabled) {
            return redirect()->route('business.pay-at-shop.index')
                ->with('error', 'Pay at shop is not enabled for your account yet. Contact CheckoutPay support or complete verification.');
        }

        $enable = $request->boolean('enable');

        if ($enable) {
            try {
                $result = $this->provisioner->provision($business, true);
            } catch (\RuntimeException $e) {
                return redirect()->route('business.pay-at-shop.index')
                    ->with('error', $e->getMessage());
            }

            $business->update(['broadcast_pay_at_shop_active' => true]);

            $redirect = redirect()->route('business.pay-at-shop.index')
                ->with('success', 'Pay at shop is now active. Customers can pay in-store via CheckoutNow.');

            if ($result['signing_key']) {
                $redirect->with('broadcast_signing_key', $result['signing_key']);
            }

            return $redirect;
        }

        $this->provisioner->setActive($business, false);
        $business->update(['broadcast_pay_at_shop_active' => false]);

        return redirect()->route('business.pay-at-shop.index')
            ->with('success', 'Pay at shop has been turned off. POS broadcasts will no longer verify.');
    }

    public function regenerateSigningKey(): RedirectResponse
    {
        $business = Auth::guard('business')->user();

        if (! $business->broadcast_pay_at_shop_enabled || ! $business->broadcast_pay_at_shop_active) {
            return redirect()->route('business.pay-at-shop.index')
                ->with('error', 'Enable Pay at shop before regenerating the signing key.');
        }

        try {
            $result = $this->provisioner->regenerateSigningKey($business);
        } catch (\RuntimeException $e) {
            return redirect()->route('business.pay-at-shop.index')
                ->with('error', $e->getMessage());
        }

        return redirect()->route('business.pay-at-shop.index')
            ->with('success', 'Signing key regenerated. Update your POS with the new key below.')
            ->with('broadcast_signing_key', $result['signing_key']);
    }
}
