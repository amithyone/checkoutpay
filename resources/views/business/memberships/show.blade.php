@extends('layouts.business')

@section('title', $membership->name)
@section('page-title', $membership->name)

@section('content')
<div class="space-y-6">
    <div class="flex justify-between items-start">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">{{ $membership->name }}</h2>
            @if($membership->category)
                <p class="text-sm text-gray-600 mt-1">{{ $membership->category->name }}</p>
            @endif
        </div>
        <div class="flex gap-2">
            <a href="{{ route('memberships.show', $membership->slug) }}" target="_blank" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                <i class="fas fa-external-link-alt mr-2"></i> View Public Page
            </a>
            <a href="{{ route('business.memberships.edit', $membership) }}" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                <i class="fas fa-edit mr-2"></i> Edit
            </a>
        </div>
    </div>

    <!-- Status Badge -->
    <div>
        @if($membership->is_active)
            <span class="px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800">Active</span>
        @else
            <span class="px-3 py-1 text-sm font-semibold rounded-full bg-gray-100 text-gray-800">Inactive</span>
        @endif
        @if($membership->is_featured)
            <span class="px-3 py-1 text-sm font-semibold rounded-full bg-yellow-100 text-yellow-800 ml-2">Featured</span>
        @endif
    </div>

    <!-- Images -->
    @if($membership->images && count($membership->images) > 0)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold mb-4">Images</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach($membership->images as $image)
                    <img src="{{ asset('storage/' . $image) }}" alt="{{ $membership->name }}" class="w-full h-48 object-cover rounded-lg">
                @endforeach
            </div>
        </div>
    @endif

    <!-- Details Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Pricing & Duration -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
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

        <!-- Who is it for -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
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

    <!-- Description -->
    @if($membership->description)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold mb-4">Description</h3>
            <p class="text-gray-700 whitespace-pre-wrap">{{ $membership->description }}</p>
        </div>
    @endif

    <!-- Features -->
    @if($membership->features && count($membership->features) > 0)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold mb-4">Features & Benefits</h3>
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

    <!-- Terms & Conditions -->
    @if($membership->terms_and_conditions)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold mb-4">Terms & Conditions</h3>
            <p class="text-gray-700 whitespace-pre-wrap">{{ $membership->terms_and_conditions }}</p>
        </div>
    @endif
</div>
@endsection
