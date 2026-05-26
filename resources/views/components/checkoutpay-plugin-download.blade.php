@props([
    'label' => null,
    'icon' => true,
])
@php
    use App\Support\CheckoutPayWordPressPlugin;
    $downloadLabel = $label ?? ($slot->isEmpty() ? 'Download Plugin' : null);
@endphp
<a
    href="{{ CheckoutPayWordPressPlugin::downloadUrl() }}"
    download="{{ CheckoutPayWordPressPlugin::slug() }}-{{ CheckoutPayWordPressPlugin::version() }}.zip"
    {{ $attributes }}
>
    @if ($icon)
        <i class="fas fa-download mr-2"></i>
    @endif
    @if ($downloadLabel)
        {{ $downloadLabel }}
    @else
        {{ $slot }}
    @endif
</a>
