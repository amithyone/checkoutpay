@extends('layouts.business')

@section('title', 'Edit campaign')
@section('page-title', 'Edit campaign')

@section('content')
<div class="space-y-6">
    <a href="{{ route('business.charity.index') }}" class="text-primary hover:underline text-sm">Back to GoFund & Charity</a>

    <form action="{{ route('business.charity.update', $campaign) }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 max-w-2xl space-y-4">
        @csrf
        @method('PUT')
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
            <input type="text" name="title" value="{{ old('title', $campaign->title) }}" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
            @error('title')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Story / Description</label>
            <textarea name="story" rows="4" class="w-full border border-gray-300 rounded-lg px-3 py-2">{{ old('story', $campaign->story) }}</textarea>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Goal amount *</label>
            <input type="number" name="goal_amount" value="{{ old('goal_amount', $campaign->goal_amount) }}" step="0.01" min="0" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Currency</label>
            <input type="text" name="currency" value="{{ old('currency', $campaign->currency) }}" maxlength="3" class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">End date (optional)</label>
            <input type="date" name="end_date" value="{{ old('end_date', $campaign->end_date ? $campaign->end_date->format('Y-m-d') : '') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Image (optional, leave empty to keep current)</label>
            <input type="file" name="image" accept="image/*" class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>
        <div class="flex gap-3">
            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">Update</button>
            <a href="{{ route('business.charity.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</a>
        </div>
    </form>
</div>
@endsection
