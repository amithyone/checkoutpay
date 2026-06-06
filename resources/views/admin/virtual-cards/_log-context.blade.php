@php
    $ctx = is_array($log->context ?? null) ? $log->context : [];
    $rawBody = $ctx['raw_body'] ?? null;
    $rawPayload = $ctx['raw_payload'] ?? null;
    $providerRequest = $ctx['provider_request'] ?? null;
    $providerResponse = $ctx['provider_response'] ?? null;
    $meta = collect($ctx)
        ->except(['raw_body', 'raw_payload', 'provider_request', 'provider_response'])
        ->filter(fn ($value) => $value !== null && $value !== '')
        ->all();
@endphp

@if($rawBody || $rawPayload || $providerRequest || $providerResponse || $meta !== [])
    <div class="space-y-2">
        @if($rawBody)
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 mb-1">Mevon raw HTTP body</p>
                <pre class="text-xs bg-amber-50 border border-amber-200 rounded p-2 overflow-x-auto max-w-2xl whitespace-pre-wrap break-all">{{ $rawBody }}</pre>
            </div>
        @endif

        @if($rawPayload)
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 mb-1">Mevon webhook payload (decoded)</p>
                <pre class="text-xs bg-amber-50 border border-amber-200 rounded p-2 overflow-x-auto max-w-2xl">{{ json_encode($rawPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        @endif

        @if($providerRequest)
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 mb-1">Mevon API request (outbound)</p>
                <pre class="text-xs bg-sky-50 border border-sky-200 rounded p-2 overflow-x-auto max-w-2xl">{{ json_encode($providerRequest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        @endif

        @if($providerResponse)
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 mb-1">Mevon API response (raw)</p>
                <pre class="text-xs bg-sky-50 border border-sky-200 rounded p-2 overflow-x-auto max-w-2xl">{{ json_encode($providerResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        @endif

        @if($meta !== [])
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 mb-1">Parsed fields</p>
                <pre class="text-xs bg-gray-50 border border-gray-200 rounded p-2 overflow-x-auto max-w-2xl">{{ json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        @endif
    </div>
@else
    —
@endif
