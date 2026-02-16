<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\UserWalletTransaction;
use App\Models\AccountNumber;
use App\Models\Setting;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class WalletController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        return view('account.wallet.index', compact('user'));
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        $business = $this->walletFundingBusiness();
        $accountNumber = $business ? AccountNumber::where('business_id', $business->id)->active()->first() : null;
        return view('account.wallet.fund', compact('user', 'business', 'accountNumber'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'name_on_transfer' => 'required|string|max:255',
        ]);

        $user = $request->user();
        $business = $this->walletFundingBusiness();
        if (! $business) {
            return back()->with('error', 'Wallet funding is not configured.')->withInput();
        }
        $accountNumber = AccountNumber::where('business_id', $business->id)->active()->first();
        if (! $accountNumber) {
            return back()->with('error', 'No account number configured for wallet funding.')->withInput();
        }

        $payment = Payment::create([
            'transaction_id' => 'WALLET-' . strtoupper(uniqid()),
            'amount' => $validated['amount'],
            'payer_name' => $validated['name_on_transfer'],
            'business_id' => $business->id,
            'user_id' => $user->id,
            'account_number' => $accountNumber->account_number,
            'status' => Payment::STATUS_PENDING,
        ]);

        return redirect()->route('user.wallet')->with('success', 'Payment instructions are below. After you transfer, your wallet will be credited once the payment is confirmed.');
    }

    public function history(Request $request): View
    {
        $user = $request->user();
        $transactions = UserWalletTransaction::where('user_id', $user->id)->latest()->paginate(20);
        return view('account.wallet.history', compact('user', 'transactions'));
    }

    private function walletFundingBusiness(): ?Business
    {
        $businessId = Setting::get('wallet_funding_business_id', null);
        if ($businessId) {
            return Business::find($businessId);
        }
        return Business::whereNotNull('id')->first();
    }
}
