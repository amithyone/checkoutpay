@extends('layouts.admin')

@section('title', 'Compose announcement')
@section('page-title', 'Compose announcement')

@section('content')
<div class="max-w-3xl space-y-6">
    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded-lg text-sm">
        Sends <strong>email</strong> and/or <strong>app push</strong> only. WhatsApp is never used.
    </div>

    <form method="POST" action="{{ route('admin.announcements.store') }}" class="bg-white border border-gray-200 rounded-lg p-6 space-y-5"
          onsubmit="return confirm('Send this announcement now? Email and/or app push only — never WhatsApp.');">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
            <input type="text" name="title" value="{{ old('title') }}" required maxlength="160"
                   class="w-full rounded-lg border-gray-300 text-sm" placeholder="e.g. New savings feature">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Message</label>
            <textarea name="body" rows="6" required maxlength="5000"
                      class="w-full rounded-lg border-gray-300 text-sm" placeholder="Plain text body for email and push">{{ old('body') }}</textarea>
        </div>

        <div>
            <div class="text-sm font-medium text-gray-700 mb-2">Audiences</div>
            <div class="space-y-2">
                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" name="audiences[]" value="wallet" class="mt-1 rounded border-gray-300" @checked(in_array('wallet', old('audiences', ['wallet', 'rentals', 'business'])))>
                    <span>Wallet / CheckoutNow users
                        <span class="block text-xs text-gray-500">~{{ number_format($reach['wallet']['emails']) }} emails · ~{{ number_format($reach['wallet']['pushes']) }} pushes</span>
                    </span>
                </label>
                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" name="audiences[]" value="rentals" class="mt-1 rounded border-gray-300" @checked(in_array('rentals', old('audiences', ['wallet', 'rentals', 'business'])))>
                    <span>Rentals users
                        <span class="block text-xs text-gray-500">~{{ number_format($reach['rentals']['emails']) }} emails · ~{{ number_format($reach['rentals']['pushes']) }} pushes</span>
                    </span>
                </label>
                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" name="audiences[]" value="business" class="mt-1 rounded border-gray-300" @checked(in_array('business', old('audiences', ['wallet', 'rentals', 'business'])))>
                    <span>Businesses
                        <span class="block text-xs text-gray-500">~{{ number_format($reach['business']['emails']) }} emails · ~{{ number_format($reach['business']['pushes']) }} pushes</span>
                    </span>
                </label>
            </div>
        </div>

        <div>
            <div class="text-sm font-medium text-gray-700 mb-2">Channels</div>
            <div class="flex flex-wrap gap-4">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="channel_email" value="1" class="rounded border-gray-300" @checked(old('channel_email', true))>
                    Email
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="channel_push" value="1" class="rounded border-gray-300" @checked(old('channel_push', true))>
                    App push
                </label>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Push deep-link screen (optional)</label>
            <select name="push_screen" class="rounded-lg border-gray-300 text-sm">
                <option value="">Default</option>
                @foreach(['home','history','saving','card','profile','support'] as $screen)
                    <option value="{{ $screen }}" @selected(old('push_screen') === $screen)>{{ $screen }}</option>
                @endforeach
            </select>
            <p class="text-xs text-gray-500 mt-1">Used for CheckoutNow wallet push; rentals/business use the same screen hint in payload.</p>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="bg-primary text-white px-5 py-2 rounded-lg hover:bg-primary/90 text-sm">Send announcement</button>
            <a href="{{ route('admin.announcements.index') }}" class="px-5 py-2 rounded-lg border border-gray-300 text-sm text-gray-700">Cancel</a>
        </div>
    </form>
</div>
@endsection
