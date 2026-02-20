@extends('layouts.account')
@section('title', 'Profile')
@section('page-title', 'Profile')
@section('content')
@php $accentColor = \App\Models\Setting::get('rentals_accent_color', '#000000'); @endphp
<div class="max-w-xl mx-auto pb-24 lg:pb-2">
    <a href="{{ route('user.dashboard') }}" class="text-sm font-medium hover:underline mb-4 inline-block" style="color: {{ $accentColor }};">← Back to dashboard</a>

    <section class="rounded-2xl bg-white border border-gray-200 shadow overflow-hidden">
        <div class="px-4 py-3 sm:px-5 sm:py-4 border-b border-gray-100 flex items-center gap-3" style="background-color: {{ $accentColor }}15;">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white flex-shrink-0" style="background-color: {{ $accentColor }};">
                <i class="fas fa-user"></i>
            </div>
            <h2 class="text-lg font-bold text-gray-800">Profile</h2>
        </div>
        <div class="p-4 sm:p-6">
            <form action="{{ route('user.profile.update') }}" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <p class="text-gray-600">{{ $user->email }}</p>
                </div>
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl text-white px-4 py-2.5 font-medium text-sm hover:opacity-90" style="background-color: {{ $accentColor }};">
                    <i class="fas fa-save"></i> Save
                </button>
            </form>
            @if($user->hasBusinessProfile())
                <div class="mt-6 pt-4 border-t border-gray-100">
                    <a href="{{ route('user.switch-to-business') }}" class="text-sm font-medium hover:underline" style="color: {{ $accentColor }};">
                        <i class="fas fa-briefcase mr-1"></i> Open Business dashboard
                    </a>
                </div>
            @endif
        </div>
    </section>
</div>
@endsection
