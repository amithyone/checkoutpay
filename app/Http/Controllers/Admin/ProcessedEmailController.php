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

        // Search by subject or sender
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('from_email', 'like', "%{$search}%")
                    ->orWhere('sender_name', 'like', "%{$search}%");
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
