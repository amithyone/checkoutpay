<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhitelistedEmailAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class WhitelistedEmailController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $whitelistedEmails = WhitelistedEmailAddress::orderBy('created_at', 'desc')->paginate(20);
        return view('admin.whitelisted-emails.index', compact('whitelistedEmails'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.whitelisted-emails.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|max:255|unique:whitelisted_email_addresses,email',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        WhitelistedEmailAddress::create([
            'email' => strtolower(trim($validated['email'])),
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        Session::flash('success', 'Whitelisted email address added successfully!');
        return redirect()->route('admin.whitelisted-emails.index');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(WhitelistedEmailAddress $whitelistedEmail)
    {
        return view('admin.whitelisted-emails.edit', compact('whitelistedEmail'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, WhitelistedEmailAddress $whitelistedEmail)
    {
        $validated = $request->validate([
            'email' => 'required|string|max:255|unique:whitelisted_email_addresses,email,' . $whitelistedEmail->id,
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        $whitelistedEmail->update([
            'email' => strtolower(trim($validated['email'])),
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        Session::flash('success', 'Whitelisted email address updated successfully!');
        return redirect()->route('admin.whitelisted-emails.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(WhitelistedEmailAddress $whitelistedEmail)
    {
        $whitelistedEmail->delete();
        Session::flash('success', 'Whitelisted email address removed successfully!');
        return redirect()->route('admin.whitelisted-emails.index');
    }
}
