@extends('layouts.marketing')

@section('title')
    @include('partials.marketing-head', ['seoPath' => '/developers/program/apply'])
@endsection

@section('content')
    <x-marketing.product-hero
        badge="Developer Program"
        title="Apply to the program"
        subtitle="You must apply and be approved before any revenue share can accrue. After we review your application, we will follow up by email or WhatsApp."
        align="left"
    />

    <x-marketing.product-section bg="white">
        <div class="max-w-xl mx-auto">
            <div class="card-marketing p-6 sm:p-8">
                <form action="{{ route('developers.program.apply.store') }}" method="post" class="space-y-5">
                    @csrf
                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Full name <span class="text-red-600">*</span></label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" required maxlength="255"
                            class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary @error('name') border-red-500 @enderror">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="business_id" class="block text-sm font-medium text-slate-700 mb-1">{{ \App\Models\Setting::get('site_name', 'CheckoutPay') }} Business ID</label>
                        <input type="text" name="business_id" id="business_id" value="{{ old('business_id') }}" maxlength="191" placeholder="Optional if registering after approval"
                            class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary @error('business_id') border-red-500 @enderror">
                        <p class="mt-1 text-xs text-slate-500">From your business dashboard. Leave blank if you do not have an account yet.</p>
                        @error('business_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="phone" class="block text-sm font-medium text-slate-700 mb-1">Phone number <span class="text-red-600">*</span></label>
                        <input type="tel" name="phone" id="phone" value="{{ old('phone') }}" required maxlength="64" autocomplete="tel"
                            class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary @error('phone') border-red-500 @enderror">
                        @error('phone')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email <span class="text-red-600">*</span></label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email"
                            class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary @error('email') border-red-500 @enderror">
                        @error('email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="whatsapp" class="block text-sm font-medium text-slate-700 mb-1">WhatsApp number <span class="text-red-600">*</span></label>
                        <input type="tel" name="whatsapp" id="whatsapp" value="{{ old('whatsapp') }}" required maxlength="64" placeholder="Include country code, e.g. +234…"
                            class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary @error('whatsapp') border-red-500 @enderror">
                        @error('whatsapp')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <fieldset>
                        <legend class="block text-sm font-medium text-slate-700 mb-2">Join the developer community <span class="text-red-600">*</span></legend>
                        <p class="text-xs text-slate-500 mb-3">Choose how you want to connect with other integrators and our team.</p>
                        <div class="space-y-2">
                            @foreach([
                                ['value' => 'slack', 'label' => 'Slack', 'icon' => 'fab fa-slack text-purple-600'],
                                ['value' => 'whatsapp', 'label' => 'WhatsApp group', 'icon' => 'fab fa-whatsapp text-green-600'],
                                ['value' => 'both', 'label' => 'Both Slack and WhatsApp', 'icon' => null],
                            ] as $option)
                                <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 cursor-pointer hover:bg-surface-container-low has-[:checked]:border-brand-primary has-[:checked]:bg-brand-primary/5">
                                    <input type="radio" name="community" value="{{ $option['value'] }}" class="text-brand-primary focus:ring-brand-primary" {{ old('community') === $option['value'] ? 'checked' : '' }}>
                                    <span class="text-sm text-midnight-deep font-medium">
                                        @if($option['icon'])<i class="{{ $option['icon'] }} mr-1"></i>@endif
                                        {{ $option['label'] }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('community')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </fieldset>
                    <button type="submit" class="btn-brand w-full">Submit application</button>
                </form>
            </div>

            <p class="mt-6 text-center text-sm text-slate-600">
                <a href="{{ route('developers.program') }}" class="text-brand-primary font-semibold hover:underline">Back to Developer Program</a>
            </p>
        </div>
    </x-marketing.product-section>
@endsection
