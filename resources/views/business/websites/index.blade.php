@extends('layouts.business')

@section('title', 'My Websites')
@section('page-title', 'My Websites')

@section('content')
<div class="space-y-6">
    <!-- Add Website Form -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Add New Website</h3>
        
        <form method="POST" action="{{ route('business.websites.store') }}">
            @csrf
            <div class="flex gap-3">
                <div class="flex-1">
                    <input type="url" name="website_url" id="website_url" 
                        value="{{ old('website_url') }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary"
                        placeholder="https://yourwebsite.com" required>
                    @error('website_url')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                    <i class="fas fa-plus mr-2"></i> Add Website
                </button>
            </div>
            <p class="mt-2 text-xs text-gray-500">New websites require admin approval before they can be used for checkout redirects.</p>
        </form>
    </div>

    <!-- Websites List -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Your Websites</h3>
        
        @if($websites->count() > 0)
            <div class="space-y-4">
                @foreach($websites as $website)
                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-2">
                                    <a href="{{ $website->website_url }}" target="_blank" 
                                        class="text-primary hover:underline font-medium">
                                        {{ $website->website_url }}
                                        <i class="fas fa-external-link-alt text-xs ml-1"></i>
                                    </a>
                                    @if($website->is_approved)
                                        <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                                            <i class="fas fa-check-circle mr-1"></i> Approved
                                        </span>
                                    @else
                                        <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
                                            <i class="fas fa-clock mr-1"></i> Pending Approval
                                        </span>
                                    @endif
                                </div>
                                <div class="text-xs text-gray-500">
                                    Added {{ $website->created_at->format('M d, Y') }}
                                    @if($website->approved_at)
                                        • Approved {{ $website->approved_at->format('M d, Y') }}
                                    @endif
                                </div>
                                @if($website->notes)
                                    <div class="mt-2 text-xs text-gray-600 bg-gray-50 p-2 rounded">
                                        <strong>Note:</strong> {{ $website->notes }}
                                    </div>
                                @endif
                            </div>
                            <div class="ml-4">
                                <form method="POST" action="{{ route('business.websites.destroy', $website) }}" 
                                    onsubmit="return confirm('Are you sure you want to remove this website?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-3 py-2 text-red-600 hover:bg-red-50 rounded-lg">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-globe text-4xl mb-3 text-gray-300"></i>
                <p>No websites added yet. Add your first website above.</p>
            </div>
        @endif
    </div>

    <!-- Information Box -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <p class="text-sm text-blue-800">
            <i class="fas fa-info-circle mr-2"></i>
            <strong>Note:</strong> You need at least one approved website before you can request an account number. 
            Approved websites are used to validate return URLs in your checkout integration.
        </p>
    </div>
</div>
@endsection
