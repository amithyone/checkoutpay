@extends('layouts.admin')

@section('title', 'Email Inbox')
@section('page-title', 'Email Inbox')

@section('content')
<div class="space-y-6">
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Total Emails</p>
                    <h3 class="text-2xl font-bold text-gray-900">{{ number_format($stats['total']) }}</h3>
                </div>
                <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-envelope text-indigo-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Matched</p>
                    <h3 class="text-2xl font-bold text-green-600">{{ number_format($stats['matched']) }}</h3>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Unmatched</p>
                    <h3 class="text-2xl font-bold text-yellow-600">{{ number_format($stats['unmatched']) }}</h3>
                </div>
                <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-clock text-yellow-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
        <form method="GET" action="{{ route('admin.processed-emails.index') }}" class="flex flex-wrap gap-4">
            <div class="flex-1 min-w-[200px]">
                <input type="text" name="search" value="{{ request('search') }}" 
                    placeholder="Search by subject, sender, email, amount, account number, or content..." 
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                    autocomplete="off">
            </div>
            
            <div class="min-w-[150px]">
                <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    <option value="">All Status</option>
                    <option value="matched" {{ request('status') === 'matched' ? 'selected' : '' }}>Matched</option>
                    <option value="unmatched" {{ request('status') === 'unmatched' ? 'selected' : '' }}>Unmatched</option>
                </select>
            </div>

            <div class="min-w-[200px]">
                <select name="email_account_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    <option value="">All Email Accounts</option>
                    @foreach($emailAccounts as $account)
                        <option value="{{ $account->id }}" {{ request('email_account_id') == $account->id ? 'selected' : '' }}>
                            {{ $account->email }}
                        </option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                <i class="fas fa-search mr-2"></i> Filter
            </button>

            @if(request()->hasAny(['search', 'status', 'email_account_id']))
                <a href="{{ route('admin.processed-emails.index') }}" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                    <i class="fas fa-times mr-2"></i> Clear
                </a>
            @endif
        </form>
    </div>

    <!-- Emails Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">From</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sender</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($emails as $email)
                    <tr class="hover:bg-gray-50 {{ !$email->is_matched ? 'bg-yellow-50/30' : '' }}">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900" title="{{ $email->subject ?? 'No Subject' }}">
                                @php
                                    $subject = $email->subject ?? 'No Subject';
                                    echo strlen($subject) > 50 ? substr($subject, 0, 50) . '...' : $subject;
                                @endphp
                            </div>
                            @if($email->emailAccount)
                                <div class="text-xs text-gray-500 mt-1">
                                    <i class="fas fa-envelope mr-1"></i>{{ $email->emailAccount->email }}
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ $email->from_email }}
                            @if($email->from_name)
                                <div class="text-xs text-gray-500">{{ $email->from_name }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            @if($email->amount)
                                <span class="font-medium">₦{{ number_format($email->amount, 2) }}</span>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ $email->sender_name ?? '-' }}
                        </td>
                        <td class="px-6 py-4">
                            @if($email->is_matched)
                                <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                                    <i class="fas fa-check-circle mr-1"></i> Matched
                                </span>
                                @if($email->matchedPayment)
                                    <div class="text-xs text-gray-500 mt-1">
                                        <a href="{{ route('admin.payments.show', $email->matchedPayment) }}" class="text-primary hover:underline">
                                            {{ $email->matchedPayment->transaction_id }}
                                        </a>
                                    </div>
                                @endif
                            @else
                                <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
                                    <i class="fas fa-clock mr-1"></i> Unmatched
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            {{ $email->email_date ? $email->email_date->format('M d, Y H:i') : $email->created_at->format('M d, Y H:i') }}
                        </td>
                        <td class="px-6 py-4">
                            <a href="{{ route('admin.processed-emails.show', $email) }}" 
                                class="text-sm text-primary hover:underline">
                                <i class="fas fa-eye mr-1"></i> View
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">
                            @if(request()->hasAny(['search', 'status', 'email_account_id']))
                                No emails found matching your search criteria.
                                <a href="{{ route('admin.processed-emails.index') }}" class="text-primary hover:underline ml-2">Clear filters</a>
                            @else
                                No emails found
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($emails->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $emails->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
