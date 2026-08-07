<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvestorPitchAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class InvestorPitchAccessController extends Controller
{
    public function index(): View
    {
        $accesses = InvestorPitchAccess::query()
            ->with([
                'createdByAdmin:id,name',
                'pageViews' => fn ($q) => $q->orderByDesc('viewed_at')->limit(12),
            ])
            ->withCount('pageViews')
            ->orderByDesc('created_at')
            ->paginate(40);

        return view('admin.investor-pitch-access.index', compact('accesses'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:190'],
            'company' => ['nullable', 'string', 'max:190'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $plainPassword = $validated['password'];

        $access = InvestorPitchAccess::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'company' => $validated['company'] ?? null,
            'access_token' => InvestorPitchAccess::generateToken(),
            'password' => Hash::make($plainPassword),
            'is_active' => $request->boolean('is_active', true),
            'notes' => $validated['notes'] ?? null,
            'created_by_admin_id' => auth('admin')->id(),
        ]);

        return redirect()
            ->route('admin.investor-pitch-access.index')
            ->with('success', 'Investor access created for '.$access->name.'.')
            ->with('created_access_id', $access->id)
            ->with('created_access_password', $plainPassword)
            ->with('created_access_url', $access->gateUrl());
    }

    public function update(Request $request, InvestorPitchAccess $investorPitchAccess): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:190'],
            'company' => ['nullable', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'min:8', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $investorPitchAccess->fill([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'company' => $validated['company'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        $plainPassword = null;
        if (! empty($validated['password'])) {
            $plainPassword = $validated['password'];
            $investorPitchAccess->password = Hash::make($plainPassword);
        }

        $investorPitchAccess->save();

        $redirect = redirect()
            ->route('admin.investor-pitch-access.index')
            ->with('success', 'Updated access for '.$investorPitchAccess->name.'.');

        if ($plainPassword !== null) {
            $redirect
                ->with('created_access_id', $investorPitchAccess->id)
                ->with('created_access_password', $plainPassword)
                ->with('created_access_url', $investorPitchAccess->gateUrl());
        }

        return $redirect;
    }

    public function regenerateLink(InvestorPitchAccess $investorPitchAccess): RedirectResponse
    {
        $investorPitchAccess->forceFill([
            'access_token' => InvestorPitchAccess::generateToken(),
        ])->save();

        return redirect()
            ->route('admin.investor-pitch-access.index')
            ->with('success', 'New personal link generated for '.$investorPitchAccess->name.'. Old link no longer works.')
            ->with('created_access_id', $investorPitchAccess->id)
            ->with('created_access_url', $investorPitchAccess->gateUrl());
    }

    public function destroy(InvestorPitchAccess $investorPitchAccess): RedirectResponse
    {
        $name = $investorPitchAccess->name;
        $investorPitchAccess->delete();

        return redirect()
            ->route('admin.investor-pitch-access.index')
            ->with('success', 'Removed investor access for '.$name.'.');
    }
}
