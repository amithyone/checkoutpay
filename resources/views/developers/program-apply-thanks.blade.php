@extends('layouts.marketing')

@section('title')
    @include('partials.marketing-head', ['seoPath' => '/developers/program/apply/thanks'])
@endsection

@section('content')
    <x-marketing.product-section bg="white">
        <div class="max-w-xl mx-auto text-center">
            <div class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 mb-4">
                <i class="fas fa-check text-2xl" aria-hidden="true"></i>
            </div>
            <h1 class="section-heading text-2xl sm:text-3xl mb-3">Application received</h1>
            <p class="section-subheading mx-auto mb-8">
                Thank you. We will review your details and contact you about approval.
                <strong class="text-midnight-deep">Revenue share only starts after you are accepted</strong> and meet attribution rules.
            </p>

            <div class="card-marketing p-6 text-left mb-8">
                <h2 class="text-sm font-semibold text-midnight-deep uppercase tracking-wide mb-3">Join the developer community</h2>
                <p class="text-sm text-slate-600 mb-4 font-medium">You asked to connect via:
                    <strong class="text-midnight-deep">
                        @if($community === 'slack')
                            Slack
                        @elseif($community === 'whatsapp')
                            WhatsApp
                        @else
                            Slack and WhatsApp
                        @endif
                    </strong>.
                </p>
                <div class="flex flex-col sm:flex-row gap-3">
                    @if(($community === 'slack' || $community === 'both') && $slackUrl)
                        <a href="{{ $slackUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-purple-600 text-white text-sm font-semibold hover:bg-purple-700">
                            <i class="fab fa-slack" aria-hidden="true"></i> Open Slack invite
                        </a>
                    @endif
                    @if(($community === 'whatsapp' || $community === 'both') && $whatsappUrl)
                        <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-wa text-white text-sm font-semibold hover:bg-wa-dark">
                            <i class="fab fa-whatsapp" aria-hidden="true"></i> Open WhatsApp community
                        </a>
                    @endif
                </div>
                @if((($community === 'slack' || $community === 'both') && ! $slackUrl) || (($community === 'whatsapp' || $community === 'both') && ! $whatsappUrl))
                    <p class="text-sm text-slate-500 mt-4 border-t border-slate-200 pt-4">
                        Invite links are not configured yet. We will send invites to the email or WhatsApp number you provided.
                    </p>
                @endif
            </div>

            <a href="{{ route('developers.program') }}" class="text-brand-primary font-semibold hover:underline text-sm">Back to Developer Program</a>
        </div>
    </x-marketing.product-section>
@endsection
