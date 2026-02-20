@extends('layouts.account')
@section('title', 'Support')
@section('page-title', 'Support')
@section('content')
@php $accentColor = \App\Models\Setting::get('rentals_accent_color', '#000000'); @endphp
<div class="max-w-4xl mx-auto pb-24 lg:pb-2">
    <a href="{{ route('user.dashboard') }}" class="text-sm font-medium hover:underline mb-4 inline-block" style="color: {{ $accentColor }};">← Back to dashboard</a>

    <section class="rounded-2xl bg-white border border-gray-200 shadow overflow-hidden">
        <div class="px-4 py-3 sm:px-5 sm:py-4 border-b border-gray-100 flex items-center gap-3" style="background-color: {{ $accentColor }}15;">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white flex-shrink-0" style="background-color: {{ $accentColor }};">
                <i class="fas fa-headset"></i>
            </div>
            <h2 class="text-lg font-bold text-gray-800">Support</h2>
        </div>
        <div class="p-4 sm:p-5">
            <p class="text-gray-600 mb-4">Raise a ticket or view replies. For product or payment support, contact the business you purchased from or use the main support centre.</p>
            <a href="{{ route('support.index') }}" class="inline-flex items-center gap-2 rounded-xl text-white px-4 py-2.5 font-medium text-sm hover:opacity-90" style="background-color: {{ $accentColor }};">
                <i class="fas fa-external-link-alt"></i> Open support centre
            </a>
        </div>
    </section>
</div>
@endsection
