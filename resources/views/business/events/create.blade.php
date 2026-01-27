@extends('layouts.business')

@section('title', 'Create Event')
@section('page-title', 'Create New Event')

@section('content')
<div class="max-w-4xl mx-auto pb-20 lg:pb-0">
    <form action="{{ route('business.events.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf

        <!-- Basic Information -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Basic Information</h2>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Event Title *</label>
                    <input type="text" name="title" value="{{ old('title') }}" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                    @error('title')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Short Description</label>
                    <textarea name="short_description" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">{{ old('short_description') }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Description</label>
                    <textarea name="description" rows="5" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">{{ old('description') }}</textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Event Image</label>
                        <input type="file" name="event_image" accept="image/*" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Banner Image</label>
                        <input type="file" name="event_banner" accept="image/*" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    </div>
                </div>
            </div>
        </div>

        <!-- Date & Time -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Date & Time</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Start Date & Time *</label>
                    <input type="datetime-local" name="start_date" value="{{ old('start_date') }}" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">End Date & Time *</label>
                    <input type="datetime-local" name="end_date" value="{{ old('end_date') }}" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                </div>
            </div>
        </div>

        <!-- Venue Information -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Venue Information</h2>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Venue Name</label>
                    <input type="text" name="venue_name" value="{{ old('venue_name') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <textarea name="venue_address" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">{{ old('venue_address') }}</textarea>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                        <input type="text" name="venue_city" value="{{ old('venue_city') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                        <input type="text" name="venue_state" value="{{ old('venue_state') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                        <input type="text" name="venue_country" value="{{ old('venue_country', 'Nigeria') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                    </div>
                </div>
            </div>
        </div>

        <!-- Capacity -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Capacity</h2>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Maximum Attendees</label>
                <input type="number" name="max_attendees" value="{{ old('max_attendees') }}" min="1" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                <p class="text-xs text-gray-500 mt-1">Leave empty for unlimited</p>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex gap-4">
            <button type="submit" name="status" value="draft" class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                Save as Draft
            </button>
            <button type="submit" name="status" value="published" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                Publish Event
            </button>
            <a href="{{ route('business.events.index') }}" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
