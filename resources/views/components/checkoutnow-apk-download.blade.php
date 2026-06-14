@props([
    'label' => null,
    'icon' => true,
])
@php
    use App\Support\CheckoutNowApp;
    $downloadLabel = $label ?? ($slot->isEmpty() ? 'Get on Google Play' : null);
@endphp
<a
    href="{{ CheckoutNowApp::playStoreUrl() }}"
    target="_blank"
    rel="noopener noreferrer"
    {{ $attributes }}
>
    @if ($icon)
        <i class="fab fa-google-play mr-2"></i>
    @endif
    @if ($downloadLabel)
        {{ $downloadLabel }}
    @else
        {{ $slot }}
    @endif
</a>
