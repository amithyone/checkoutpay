<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmailAccountController extends Controller
{
    /**
     * Display a listing of email accounts
     */
    public function index()
    {
        $emailAccounts = EmailAccount::latest()->paginate(15);
        return view('admin.email-accounts.index', compact('emailAccounts'));
    }

    /**
     * Show the form for creating a new email account
     */
    public function create()
    {
        return view('admin.email-accounts.create');
    }

    /**
     * Store a newly created email account
     */
    public function store(Request $request)
    {
        $method = $request->input('method', 'imap');
        
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:email_accounts,email',
            'method' => 'required|in:imap,gmail_api',
            'folder' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
            'allowed_senders' => 'nullable|string', // Will be converted to array
        ];

        if ($method === 'imap') {
            $rules['host'] = 'required|string|max:255';
            $rules['port'] = 'required|integer|min:1|max:65535';
            $rules['encryption'] = 'required|in:ssl,tls,none';
            $rules['password'] = 'required|string';
            $rules['validate_cert'] = 'boolean';
        } else {
            $rules['gmail_credentials_path'] = 'required|string|max:255';
            $rules['gmail_token_path'] = 'nullable|string|max:255';
        }

        $validated = $request->validate($rules);

        // Set defaults for Gmail API
        if ($method === 'gmail_api') {
            $validated['host'] = 'imap.gmail.com';
            $validated['port'] = 993;
            $validated['encryption'] = 'ssl';
            $validated['password'] = ''; // Not needed for Gmail API
            $validated['gmail_authorized'] = false;
        }

        try {
            EmailAccount::create($validated);
            return redirect()->route('admin.email-accounts.index')
                ->with('success', 'Email account created successfully!');
        } catch (\Exception $e) {
            Log::error('Error creating email account', ['error' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Failed to create email account: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for editing an email account
     */
    public function edit(EmailAccount $emailAccount)
    {
        return view('admin.email-accounts.edit', compact('emailAccount'));
    }

    /**
     * Update the specified email account
     */
    public function update(Request $request, EmailAccount $emailAccount)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:email_accounts,email,' . $emailAccount->id,
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'encryption' => 'required|in:ssl,tls,none',
            'validate_cert' => 'boolean',
            'password' => 'nullable|string', // Optional - only update if provided
            'folder' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        // Don't update password if not provided
        // Check if password is actually empty (not just whitespace)
        // But preserve spaces if password is provided (Gmail App Passwords have spaces)
        if (!isset($validated['password']) || trim($validated['password']) === '') {
            unset($validated['password']);
        }
        // If password is provided, preserve it exactly as entered (including spaces)

        try {
            $emailAccount->update($validated);
            return redirect()->route('admin.email-accounts.index')
                ->with('success', 'Email account updated successfully!');
        } catch (\Exception $e) {
            Log::error('Error updating email account', ['error' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Failed to update email account: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified email account
     */
    public function destroy(EmailAccount $emailAccount)
    {
        try {
            // Check if email account is being used by businesses
            if ($emailAccount->businesses()->count() > 0) {
                return back()->with('error', 'Cannot delete email account that is assigned to businesses.');
            }

            $emailAccount->delete();
            return redirect()->route('admin.email-accounts.index')
                ->with('success', 'Email account deleted successfully!');
        } catch (\Exception $e) {
            Log::error('Error deleting email account', ['error' => $e->getMessage()]);
            return back()->with('error', 'Failed to delete email account: ' . $e->getMessage());
        }
    }

    /**
     * Test email account connection
     */
    public function testConnection(EmailAccount $emailAccount)
    {
        try {
            $result = $emailAccount->testConnection();
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
