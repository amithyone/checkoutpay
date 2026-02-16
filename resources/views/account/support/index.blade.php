@extends('layouts.account')
@section('title', 'Support')
@section('page-title', 'Support')
@section('content')
<div class="max-w-4xl mx-auto">
    <a href="{{ route('user.dashboard') }}" class="text-sm font-medium text-primary hover:underline mb-4 inline-block">← Back to dashboard</a>
    <h2 class="text-lg font-bold text-gray-800 mb-4">Support</h2>
    <p class="text-gray-600 mb-4">Raise a ticket or view replies. For product or payment support, contact the business you purchased from or use the main <a href="{{ route('support.index') }}" class="text-primary hover:underline">Support</a> page.</p>
</div>
@endsection
