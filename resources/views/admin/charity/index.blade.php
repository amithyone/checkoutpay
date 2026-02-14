@extends('layouts.admin')

@section('title', 'Charity Campaigns')
@section('page-title', 'Charity / GoFund Campaigns')

@section('content')
<div class="space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">All Campaigns</h3>
            <p class="text-sm text-gray-600">Approve and manage charity campaigns</p>
        </div>
        <a href="{{ route('admin.charity.create') }}" class="px-4 py-2 bg-primary text-white rounded-lg">Create Campaign</a>
    </div>

    @if(session('success'))
        <p class="text-green-600 text-sm">{{ session('success') }}</p>
    @endif

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        @if($campaigns->isEmpty())
            <div class="p-12 text-center text-gray-600">
                <p>No charity campaigns yet.</p>
                <a href="{{ route('admin.charity.create') }}" class="inline-block mt-4 px-4 py-2 bg-primary text-white rounded-lg">Create Campaign</a>
            </div>
        @else
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Business</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Goal / Raised</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($campaigns as $campaign)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $campaign->title }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $campaign->business->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm">{{ $campaign->currency }} {{ number_format($campaign->raised_amount, 0) }} / {{ number_format($campaign->goal_amount, 0) }}</td>
                            <td class="px-4 py-3 text-sm">{{ $campaign->status }}</td>
                            <td class="px-4 py-3 text-right text-sm">
                                <a href="{{ route('charity.show', $campaign->slug) }}" target="_blank" class="text-primary mr-3">View</a>
                                <a href="{{ route('admin.charity.show', $campaign) }}" class="mr-3">Admin</a>
                                <a href="{{ route('admin.charity.edit', $campaign) }}" class="mr-3">Edit</a>
                                <form action="{{ route('admin.charity.destroy', $campaign) }}" method="POST" class="inline" onsubmit="return confirm('Delete?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="px-4 py-3 border-t">{{ $campaigns->links() }}</div>
        @endif
    </div>
</div>
@endsection
