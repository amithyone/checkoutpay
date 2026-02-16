@extends('layouts.account')
@section('title', 'Profile')
@section('page-title', 'Profile')
@section('content')
<div class="max-w-xl mx-auto">
    <a href="{{ route('user.dashboard') }}" class="text-sm font-medium text-primary hover:underline mb-4 inline-block">Back to dashboard</a>
    <h2 class="text-lg font-bold text-gray-800 mb-4">Profile</h2>
    <form action="{{ route('user.profile.update') }}" method="POST" class="bg-white rounded-xl border border-gray-200 p-6">
        @csrf
        @method('PUT')
        <div class="mb-4">
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
            <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <p class="text-gray-600">{{ $user->email }}</p>
        </div>
        <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 font-medium">Save</button>
    </form>
    @if($user->hasBusinessProfile())
    <p class="mt-4 text-sm text-gray-600"><a href="{{ route('user.switch-to-business') }}" class="text-primary hover:underline">Open Business dashboard</a></p>
    @endif
</div>
@endsection
