@extends('layouts.account')
@section('title', 'Reviews')
@section('page-title', 'Reviews')
@section('content')
<div class="max-w-4xl mx-auto">
    <a href="{{ route('user.dashboard') }}" class="text-sm font-medium text-primary hover:underline mb-4 inline-block">← Back to dashboard</a>
    <h2 class="text-lg font-bold text-gray-800 mb-4">Reviews</h2>
    <p class="text-gray-600">No reviews yet. Reviews you leave for businesses will appear here.</p>
</div>
@endsection
