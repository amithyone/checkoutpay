<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Renter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RenterController extends Controller
{
    /**
     * List all rental (renter) users for admin.
     */
    public function index(Request $request): View
    {
        $query = Renter::query()->latest('updated_at');

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        if ($request->has('active') && $request->query('active') !== '' && $request->query('active') !== null) {
            $query->where('is_active', $request->boolean('active'));
        }

        $renters = $query->paginate(24)->withQueryString();

        return view('admin.renters.index', [
            'renters' => $renters,
            'search' => $request->query('q', ''),
            'activeFilter' => $request->query('active'),
        ]);
    }

    public function show(Renter $renter): View
    {
        return view('admin.renters.show', [
            'renter' => $renter,
        ]);
    }

    public function update(Request $request, Renter $renter): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:renters,email,'.$renter->id,
            'phone' => 'nullable|string|max:40',
            'address' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
            'balance_audit_exempt' => 'nullable|boolean',
        ]);

        $renter->update([
            'name' => $validated['name'],
            'email' => strtolower(trim($validated['email'])),
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'balance_audit_exempt' => $request->boolean('balance_audit_exempt'),
        ]);

        return redirect()
            ->route('admin.renters.show', $renter)
            ->with('success', 'Rental user profile updated.');
    }

    public function updateBalance(Request $request, Renter $renter): RedirectResponse
    {
        $admin = auth('admin')->user();
        if (! $admin || ! $admin->canUpdateBusinessBalance()) {
            abort(403, 'Only super admins can update renter wallet balances.');
        }

        $validated = $request->validate([
            'wallet_balance' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $old = (float) $renter->wallet_balance;
        $new = round((float) $validated['wallet_balance'], 2);
        $renter->update(['wallet_balance' => $new]);

        $notes = trim((string) ($validated['notes'] ?? ''));
        $message = 'Wallet balance updated from ₦'.number_format($old, 2).' to ₦'.number_format($new, 2);
        if ($notes !== '') {
            $message .= ' ('.$notes.')';
        }

        return redirect()
            ->route('admin.renters.show', $renter)
            ->with('success', $message);
    }
}
