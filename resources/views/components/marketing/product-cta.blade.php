@props([
    'title' => 'Get started today',
    'subtitle' => 'Create your business account and start accepting payments in minutes.',
    'primaryUrl' => null,
    'primaryLabel' => 'Create your account',
    'secondaryUrl' => null,
    'secondaryLabel' => 'View pricing',
])

<section class="py-16 sm:py-20 bg-midnight-deep text-white">
    <div class="max-w-container mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-2xl sm:text-3xl md:text-4xl font-black mb-4">{{ $title }}</h2>
        @if($subtitle)
            <p class="text-slate-300 font-medium text-base md:text-lg mb-8 max-w-2xl mx-auto">{{ $subtitle }}</p>
        @endif
        <div class="flex flex-col sm:flex-row justify-center items-center gap-4">
            @if($primaryUrl)
                <a href="{{ $primaryUrl }}" class="btn-brand bg-white text-midnight-deep hover:bg-slate-100 w-full sm:w-auto">
                    {{ $primaryLabel }}
                </a>
            @endif
            @if($secondaryUrl)
                <a href="{{ $secondaryUrl }}" class="btn-brand-outline border-white/30 text-white hover:bg-white/10 w-full sm:w-auto">
                    {{ $secondaryLabel }}
                </a>
            @endif
            @if(isset($actions))
                {{ $actions }}
            @endif
        </div>
    </div>
</section>
