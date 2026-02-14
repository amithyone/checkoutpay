@extends('layouts.business')

@section('title', 'GoFund and Charity')
@section('page-title', 'GoFund and Charity')

@section('content')
<div class="space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Your charity campaigns</h3>
            <p class="text-sm text-gray-600">Create campaigns. Once approved by admin they appear on the public page.</p>
        </div>
        <a href="{{ route('business.charity.create') }}" class="px-4 py-2 bg-primary text-white rounded-lg">Create campaign</a>
    </div>

    @if(session('success'))
        <p class="text-green-600 text-sm">{{ session('success') }}</p>
    @endif

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        @if($campaigns->isEmpty())
            <div class="p-12 text-center text-gray-600">
                <p>You have no campaigns yet.</p>
                <a href="{{ route('business.charity.create') }}" class="inline-block mt-4 px-4 py-2 bg-primary text-white rounded-lg">Create campaign</a>
            </div>
        @else
            <ul class="divide-y divide-gray-200">
                @foreach($campaigns as $campaign)
                    <li class="p-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <span class="font-medium text-gray-900">{{ $campaign->title }}</span>
                            <span class="ml-2 text-xs">{{ $campaign->status }}</span>
                            <p class="text-sm text-gray-500 mt-1">{{ $campaign->currency }} {{ number_format($campaign->raised_amount, 0) }} / {{ number_format($campaign->goal_amount, 0) }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('charity.show', $campaign->slug) }}" target="_blank" class="text-primary text-sm">View public</a>
                            <a href="{{ route('business.charity.edit', $campaign) }}" class="text-primary text-sm">Edit</a>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="px-4 py-3 border-t">{{ $campaigns->links() }}</div>
        @endif
    </div>

    <p class="text-sm"><a href="{{ route('charity.index') }}" target="_blank" class="text-primary">View public charity page</a></p>
</div>
@endsection
