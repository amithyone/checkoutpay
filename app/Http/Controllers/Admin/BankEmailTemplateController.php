<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankEmailTemplate;
use Illuminate\Http\Request;

class BankEmailTemplateController extends Controller
{
    /**
     * Display a listing of bank email templates
     */
    public function index()
    {
        $templates = BankEmailTemplate::orderBy('priority', 'desc')
            ->orderBy('bank_name')
            ->paginate(15);
        
        return view('admin.bank-email-templates.index', compact('templates'));
    }

    /**
     * Show the form for creating a new template
     */
    public function create()
    {
        return view('admin.bank-email-templates.create');
    }

    /**
     * Store a newly created template
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'bank_name' => 'required|string|max:255',
            'sender_email' => 'nullable|email|max:255',
            'sender_domain' => 'nullable|string|max:255',
            'sample_html' => 'nullable|string',
            'sample_text' => 'nullable|string',
            'amount_pattern' => 'nullable|string',
            'sender_name_pattern' => 'nullable|string',
            'account_number_pattern' => 'nullable|string',
            'amount_field_label' => 'nullable|string|max:255',
            'sender_name_field_label' => 'nullable|string|max:255',
            'account_number_field_label' => 'nullable|string|max:255',
            'extraction_notes' => 'nullable|string',
            'is_active' => 'boolean',
            'priority' => 'integer|min:0|max:100',
        ]);

        BankEmailTemplate::create($validated);

        return redirect()->route('admin.bank-email-templates.index')
            ->with('success', 'Bank email template created successfully!');
    }

    /**
     * Display the specified template
     */
    public function show(BankEmailTemplate $bankEmailTemplate)
    {
        return view('admin.bank-email-templates.show', compact('bankEmailTemplate'));
    }

    /**
     * Show the form for editing the specified template
     */
    public function edit(BankEmailTemplate $bankEmailTemplate)
    {
        return view('admin.bank-email-templates.edit', compact('bankEmailTemplate'));
    }

    /**
     * Update the specified template
     */
    public function update(Request $request, BankEmailTemplate $bankEmailTemplate)
    {
        $validated = $request->validate([
            'bank_name' => 'required|string|max:255',
            'sender_email' => 'nullable|email|max:255',
            'sender_domain' => 'nullable|string|max:255',
            'sample_html' => 'nullable|string',
            'sample_text' => 'nullable|string',
            'amount_pattern' => 'nullable|string',
            'sender_name_pattern' => 'nullable|string',
            'account_number_pattern' => 'nullable|string',
            'amount_field_label' => 'nullable|string|max:255',
            'sender_name_field_label' => 'nullable|string|max:255',
            'account_number_field_label' => 'nullable|string|max:255',
            'extraction_notes' => 'nullable|string',
            'is_active' => 'boolean',
            'priority' => 'integer|min:0|max:100',
        ]);

        $bankEmailTemplate->update($validated);

        return redirect()->route('admin.bank-email-templates.index')
            ->with('success', 'Bank email template updated successfully!');
    }

    /**
     * Remove the specified template
     */
    public function destroy(BankEmailTemplate $bankEmailTemplate)
    {
        $bankEmailTemplate->delete();

        return redirect()->route('admin.bank-email-templates.index')
            ->with('success', 'Bank email template deleted successfully!');
    }
}
