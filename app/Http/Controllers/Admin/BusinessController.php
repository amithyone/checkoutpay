<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\EmailAccount;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BusinessController extends Controller
{
    public function index(Request $request): View
    {
        $query = Business::withCount(['payments', 'withdrawalRequests'])->latest();

        if ($request->has('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $businesses = $query->paginate(20);

        return view('admin.businesses.index', compact('businesses'));
    }

    public function create(): View
    {
        $emailAccounts = EmailAccount::where('is_active', true)->get();
        return view('admin.businesses.create', compact('emailAccounts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:businesses,email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'webhook_url' => 'nullable|url',
            'email_account_id' => 'nullable|exists:email_accounts,id',
            'is_active' => 'boolean',
        ]);

        Business::create($validated);

        return redirect()->route('admin.businesses.index')
            ->with('success', 'Business created successfully');
    }

    public function show(Business $business): View
    {
        $business->load(['payments', 'withdrawalRequests', 'accountNumbers']);
        return view('admin.businesses.show', compact('business'));
    }

    public function edit(Business $business): View
    {
        $emailAccounts = EmailAccount::where('is_active', true)->get();
        return view('admin.businesses.edit', compact('business', 'emailAccounts'));
    }

    public function update(Request $request, Business $business): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:businesses,email,' . $business->id,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'webhook_url' => 'nullable|url',
            'email_account_id' => 'nullable|exists:email_accounts,id',
            'is_active' => 'boolean',
        ]);

        $business->update($validated);

        return redirect()->route('admin.businesses.index')
            ->with('success', 'Business updated successfully');
    }

    public function regenerateApiKey(Business $business): RedirectResponse
    {
        $business->api_key = Business::generateApiKey();
        $business->save();

        return redirect()->route('admin.businesses.show', $business)
            ->with('success', 'API key regenerated successfully');
    }
}
