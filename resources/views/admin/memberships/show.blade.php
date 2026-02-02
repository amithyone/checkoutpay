@extends('layouts.admin')

@section('title', $membership->name)

@section('content')
<div class="p-6">
    <div class="flex justify-between items-start mb-6">
        <div>
            <h1 class="text-2xl font-bold">{{ $membership->name }}</h1>
            <p class="text-gray-600 mt-1">Business: {{ $membership->business->name }}</p>
        </div>
        <a href="{{ route('admin.memberships.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
            Back to List
        </a>
    </div>

    <!-- Status Update Form -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="text-lg font-semibold mb-4">Update Status</h3>
        <form action="{{ route('admin.memberships.update-status', $membership) }}" method="POST" class="flex gap-4 items-end">
            @csrf
            <div>
                <label class="flex items-center">
                    <input type="checkbox" name="is_active" value="1" {{ $membership->is_active ? 'checked' : '' }}
                        class="rounded border-gray-300 text-primary focus:ring-primary">
                    <span class="ml-2 text-sm text-gray-700">Active</span>
                </label>
            </div>
            <div>
                <label class="flex items-center">
                    <input type="checkbox" name="is_featured" value="1" {{ $membership->is_featured ? 'checked' : '' }}
                        class="rounded border-gray-300 text-primary focus:ring-primary">
                    <span class="ml-2 text-sm text-gray-700">Featured</span>
                </label>
            </div>
            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                Update Status
            </button>
        </form>
    </div>

    <!-- Details -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Pricing & Duration</h3>
            <div class="space-y-3">
                <div>
                    <p class="text-sm text-gray-600">Price</p>
                    <p class="text-2xl font-bold text-primary">{{ $membership->currency }} {{ number_format($membership->price, 2) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Duration</p>
                    <p class="text-lg font-semibold">{{ $membership->formatted_duration }}</p>
                </div>
                @if($membership->max_members)
                    <div>
                        <p class="text-sm text-gray-600">Members</p>
                        <p class="text-lg font-semibold">{{ $membership->current_members }} / {{ $membership->max_members }}</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Who is it for?</h3>
            @if($membership->who_is_it_for)
                <p class="text-gray-700 mb-3">{{ $membership->who_is_it_for }}</p>
            @endif
            @if($membership->who_is_it_for_suggestions && count($membership->who_is_it_for_suggestions) > 0)
                <div class="flex flex-wrap gap-2">
                    @foreach($membership->who_is_it_for_suggestions as $suggestion)
                        <span class="px-3 py-1 text-sm bg-primary/10 text-primary rounded-full">{{ $suggestion }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @if($membership->description)
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h3 class="text-lg font-semibold mb-4">Description</h3>
            <p class="text-gray-700 whitespace-pre-wrap">{{ $membership->description }}</p>
        </div>
    @endif

    @if($membership->features && count($membership->features) > 0)
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h3 class="text-lg font-semibold mb-4">Features</h3>
            <ul class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach($membership->features as $feature)
                    <li class="flex items-start">
                        <i class="fas fa-check text-green-500 mr-2 mt-1"></i>
                        <span class="text-gray-700">{{ $feature }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($membership->images && count($membership->images) > 0)
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Images</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach($membership->images as $image)
                    <img src="{{ asset('storage/' . $image) }}" alt="{{ $membership->name }}" class="w-full h-48 object-cover rounded-lg">
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
