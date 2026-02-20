@extends('layouts.account')
@section('title', 'Reviews')
@section('page-title', 'Reviews')
@section('content')
@php $accentColor = \App\Models\Setting::get('rentals_accent_color', '#000000'); @endphp
<div class="max-w-4xl mx-auto pb-24 lg:pb-2">
    <a href="{{ route('user.dashboard') }}" class="text-sm font-medium hover:underline mb-4 inline-block" style="color: {{ $accentColor }};">← Back to dashboard</a>

    <section class="rounded-2xl bg-white border border-gray-200 shadow overflow-hidden">
        <div class="px-4 py-3 sm:px-5 sm:py-4 border-b border-gray-100 flex items-center gap-3" style="background-color: {{ $accentColor }}15;">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white flex-shrink-0" style="background-color: {{ $accentColor }};">
                <i class="fas fa-star"></i>
            </div>
            <h2 class="text-lg font-bold text-gray-800">Reviews</h2>
        </div>
        <div class="p-4 sm:p-5">
            <p class="text-gray-600">No reviews yet. Reviews you leave for businesses will appear here.</p>
        </div>
    </section>
</div>
@endsection
