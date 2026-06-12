@props([
    'label' => null,
    'icon' => true,
])
@php
    use App\Support\CheckoutNowApp;
    $downloadLabel = $label ?? ($slot->isEmpty() ? 'Download Android APK' : null);
@endphp
<a
    href="{{ CheckoutNowApp::androidApkDownloadUrl() }}"
    download="checkoutnow-android.apk"
    {{ $attributes }}
>
    @if ($icon)
        <i class="fab fa-android mr-2"></i>
    @endif
    @if ($downloadLabel)
        {{ $downloadLabel }}
    @else
        {{ $slot }}
    @endif
</a>
