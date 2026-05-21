@php
    $supportWidgetCssVer = @filemtime(public_path('css/support-widget.css')) ?: time();
    $supportWidgetJsVer = @filemtime(public_path('js/support-widget.js')) ?: time();
@endphp
<link rel="stylesheet" href="{{ asset('css/support-widget.css') }}?v={{ $supportWidgetCssVer }}">
<div id="cp-support-widget-root">
    <div id="cp-support-panel">
        <div class="cp-support-header">
            <span class="font-semibold text-sm">CheckoutPay Support</span>
            <button type="button" id="cp-support-close" class="text-white/90 hover:text-white text-lg leading-none" aria-label="Close">&times;</button>
        </div>
        <div id="cp-support-body" class="cp-support-body"></div>
        <div id="cp-support-footer" class="cp-support-footer" style="display:none">
            <textarea id="cp-support-composer" rows="2" placeholder="Type a message…"></textarea>
            <button type="button" id="cp-support-send">Send</button>
        </div>
    </div>
    <button type="button" id="cp-support-launcher" aria-label="Open support chat">
        <i class="fas fa-comments"></i>
    </button>
</div>
<script>
    window.CP_SUPPORT_API_BASE = @json(url('/api/v1'));
    window.CP_SUPPORT_POLL_MS = {{ (int) config('support.poll_interval_seconds', 4) * 1000 }};
</script>
<script src="{{ asset('js/support-widget.js') }}?v={{ $supportWidgetJsVer }}"></script>
