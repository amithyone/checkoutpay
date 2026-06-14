@extends('layouts.marketing')

@section('title')
    @include('partials.marketing-head', ['seoPath' => '/developers/program/apply/thanks'])
@endsection

@section('content')
<div class="max-w-xl mx-auto px-4 sm:px-6 py-12 sm:py-16 text-center">
        <div class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-green-100 text-green-700 mb-4">
            <i class="fas fa-check text-2xl"></i>
        </div>
        <h1 class="text-2xl sm:text-3xl font-bold text-midnight-deep mb-3">Application received</h1>
        <p class="text-slate-600 mb-8">
            Thank you. We will review your details and contact you about approval. <strong class="text-midnight-deep">Revenue share only starts after you are accepted into the program</strong> and meet attribution rules (for example a valid Business ID on qualifying integrations).
        </p>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 text-left mb-8">
            <h2 class="text-sm font-semibold text-midnight-deep uppercase tracking-wide mb-3">Join the developer community</h2>
            <p class="text-sm text-slate-600 mb-4">You asked to connect via:
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
                    <a href="{{ $slackUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center gap-2 px-4 py-3 rounded-lg bg-purple-600 text-white text-sm font-medium hover:bg-purple-700">
                        <i class="fab fa-slack"></i> Open Slack invite
                    </a>
                @endif
                @if(($community === 'whatsapp' || $community === 'both') && $whatsappUrl)
                    <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center gap-2 px-4 py-3 rounded-lg bg-green-600 text-white text-sm font-medium hover:bg-green-700">
                        <i class="fab fa-whatsapp"></i> Open WhatsApp community
                    </a>
                @endif
            </div>
            @if((($community === 'slack' || $community === 'both') && ! $slackUrl) || (($community === 'whatsapp' || $community === 'both') && ! $whatsappUrl))
                <p class="text-sm text-slate-500 mt-4 border-t border-gray-100 pt-4">
                    Invite links are not configured on this server yet. We will send Slack or WhatsApp invites to the email or WhatsApp number you provided. To configure links for this page, set <code class="text-xs bg-gray-100 px-1 rounded">DEVELOPER_PROGRAM_SLACK_URL</code> and/or <code class="text-xs bg-gray-100 px-1 rounded">DEVELOPER_PROGRAM_WHATSAPP_URL</code> in your environment.
                </p>
            @endif
        </div>

        <a href="{{ route('developers.program') }}" class="text-primary font-medium hover:underline text-sm">Back to Developer Program</a>
    </div>
@endsection
