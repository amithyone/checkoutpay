<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProcessedEmail;
use Illuminate\Http\Request;

class ProcessedEmailController extends Controller
{
    /**
     * Display inbox of stored emails
     */
    public function index(Request $request)
    {
        $query = ProcessedEmail::with('emailAccount', 'matchedPayment')
            ->latest();

        // Filter by status
        if ($request->has('status')) {
            if ($request->status === 'matched') {
                $query->where('is_matched', true);
            } elseif ($request->status === 'unmatched') {
                $query->where('is_matched', false);
            }
        }

        // Filter by email account
        if ($request->has('email_account_id')) {
            $query->where('email_account_id', $request->email_account_id);
        }

        // Search by multiple fields
        if ($request->has('search') && !empty(trim($request->search))) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                // Search in subject
                $q->where('subject', 'like', "%{$search}%")
                    // Search in from email
                    ->orWhere('from_email', 'like', "%{$search}%")
                    // Search in from name
                    ->orWhere('from_name', 'like', "%{$search}%")
                    // Search in sender name
                    ->orWhere('sender_name', 'like', "%{$search}%")
                    // Search in account number
                    ->orWhere('account_number', 'like', "%{$search}%")
                    // Search in message ID
                    ->orWhere('message_id', 'like', "%{$search}%")
                    // Search in text body (first 500 chars for performance)
                    ->orWhereRaw('SUBSTRING(text_body, 1, 500) LIKE ?', ["%{$search}%"])
                    // Search in HTML body (first 500 chars for performance)
                    ->orWhereRaw('SUBSTRING(html_body, 1, 500) LIKE ?', ["%{$search}%"]);
                
                // If search looks like a number, also search in amount
                if (is_numeric($search)) {
                    $amount = (float) str_replace(',', '', $search);
                    $q->orWhere('amount', $amount);
                }
            });
        }

        $emails = $query->paginate(20);

        // Statistics
        $stats = [
            'total' => ProcessedEmail::count(),
            'matched' => ProcessedEmail::where('is_matched', true)->count(),
            'unmatched' => ProcessedEmail::where('is_matched', false)->count(),
        ];

        // Get email accounts for filter
        $emailAccounts = \App\Models\EmailAccount::all();

        return view('admin.processed-emails.index', compact('emails', 'stats', 'emailAccounts'));
    }

    /**
     * Show email details
     */
    public function show(ProcessedEmail $processedEmail)
    {
        $processedEmail->load('emailAccount', 'matchedPayment.business');
        
        return view('admin.processed-emails.show', compact('processedEmail'));
    }
}
