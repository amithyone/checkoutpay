@extends('layouts.admin')

@section('title', 'Announcement')
@section('page-title', 'Announcement')

@section('content')
<div class="max-w-3xl space-y-6">
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">{{ session('error') }}</div>
    @endif

    <div class="flex items-center justify-between gap-3">
        <a href="{{ route('admin.announcements.index') }}" class="text-sm text-primary hover:underline">← All announcements</a>
        @if(in_array($item->status, ['queued', 'failed'], true))
            <form method="POST" action="{{ route('admin.announcements.process', $item) }}" onsubmit="return confirm('Process this announcement now?');">
                @csrf
                <button type="submit" class="bg-gray-900 text-white text-sm px-4 py-2 rounded-lg">Process now</button>
            </form>
        @endif
    </div>

    <div class="bg-white border border-gray-200 rounded-lg p-6 space-y-4">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h3 class="text-xl font-semibold text-gray-900">{{ $item->title }}</h3>
                <p class="text-xs text-gray-500 mt-1">
                    {{ $item->created_at?->format('Y-m-d H:i') }}
                    · {{ $item->admin?->name ?? 'Admin' }}
                    · <span class="uppercase">{{ $item->status }}</span>
                </p>
            </div>
        </div>

        <div class="text-sm text-gray-800 whitespace-pre-wrap border-t border-gray-100 pt-4">{{ $item->body }}</div>

        <dl class="grid grid-cols-2 gap-3 text-sm border-t border-gray-100 pt-4">
            <div><dt class="text-gray-500">Audiences</dt><dd class="font-medium">{{ implode(', ', $item->audienceList()) }}</dd></div>
            <div>
                <dt class="text-gray-500">Channels</dt>
                <dd class="font-medium">
                    @if($item->channel_email) Email @endif
                    @if($item->channel_email && $item->channel_push)·@endif
                    @if($item->channel_push) App push @endif
                    <span class="text-gray-400 font-normal">(never WhatsApp)</span>
                </dd>
            </div>
            <div><dt class="text-gray-500">Emails sent / failed / skipped</dt><dd class="font-medium">{{ $item->emails_sent }} / {{ $item->emails_failed }} / {{ $item->emails_skipped }}</dd></div>
            <div><dt class="text-gray-500">Pushes sent / failed / skipped</dt><dd class="font-medium">{{ $item->pushes_sent }} / {{ $item->pushes_failed }} / {{ $item->pushes_skipped }}</dd></div>
            @if($item->push_screen)
                <div><dt class="text-gray-500">Push screen</dt><dd class="font-medium">{{ $item->push_screen }}</dd></div>
            @endif
            <div><dt class="text-gray-500">Estimated recipients</dt><dd class="font-medium">{{ number_format($item->recipients_estimated) }}</dd></div>
        </dl>

        @if($item->error_summary)
            <div class="bg-red-50 border border-red-100 rounded-lg p-3 text-xs text-red-800 whitespace-pre-wrap">{{ $item->error_summary }}</div>
        @endif
    </div>
</div>
@endsection
