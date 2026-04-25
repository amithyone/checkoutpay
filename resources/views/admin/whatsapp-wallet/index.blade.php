@extends('layouts.admin')

@section('title', 'WhatsApp wallet')
@section('page-title', 'WhatsApp wallet')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">
            <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg border border-gray-200 p-5 shadow-sm">
            <p class="text-sm text-gray-500">Wallets (total)</p>
            <p class="text-2xl font-bold text-gray-900">{{ number_format($walletTotal) }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-5 shadow-sm">
            <p class="text-sm text-gray-500">Wallets with PIN</p>
            <p class="text-2xl font-bold text-gray-900">{{ number_format($walletsWithPin) }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-5 shadow-sm">
            <p class="text-sm text-gray-500">Transactions (all time)</p>
            <p class="text-2xl font-bold text-gray-900">{{ number_format($txTotal) }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-5 shadow-sm">
            <p class="text-sm text-gray-500">Transactions (last 7 days)</p>
            <p class="text-2xl font-bold text-primary">{{ number_format($txLast7d) }}</p>
            <p class="text-xs text-gray-400 mt-1">30d: {{ number_format($txLast30d) }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-gray-900 mb-3">Wallets by detected country (phone prefix)</h3>
            @if($walletsByCountry === [])
                <p class="text-sm text-gray-500">No wallets yet.</p>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach($walletsByCountry as $label => $cnt)
                        <li class="py-2 flex justify-between text-sm">
                            <span>{{ $label }}</span>
                            <span class="font-medium">{{ number_format($cnt) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-gray-900 mb-3">Enabled regions (Evolution instances)</h3>
            <p class="text-xs text-gray-500 mb-3">Merged: <code class="bg-gray-100 px-1 rounded">config/whatsapp_wallet_regions.php</code> + env + <strong>admin extra rows</strong> (see Integration settings). Same dial or instance name is overridden by admin.</p>
            @if($regions === [])
                <p class="text-sm text-gray-500">No instances configured.</p>
            @else
                <ul class="space-y-2 text-sm">
                    @foreach($regions as $instanceName => $row)
                        @if(is_array($row))
                            <li class="border border-gray-100 rounded-lg p-3">
                                <div class="font-medium text-gray-900">{{ $instanceName }}</div>
                                <div class="text-gray-600">{{ $row['label'] ?? $row['country'] ?? '—' }} · {{ $row['currency'] ?? '' }}</div>
                                @if(!empty($row['features']) && is_array($row['features']))
                                    <div class="text-xs text-gray-500 mt-1">
                                        @foreach($row['features'] as $k => $on)
                                            @if($on)<span class="inline-block bg-gray-100 rounded px-1 mr-1">{{ $k }}</span>@endif
                                        @endforeach
                                    </div>
                                @endif
                            </li>
                        @endif
                    @endforeach
                </ul>
            @endif
            @if(is_array($dialMap) && $dialMap !== [])
                <h4 class="text-sm font-semibold text-gray-800 mt-4 mb-2">Dial → country map</h4>
                <ul class="text-xs text-gray-600 space-y-1">
                    @foreach($dialMap as $m)
                        @if(is_array($m))
                            <li>+{{ $m['dial'] ?? '' }} → {{ $m['label'] ?? $m['country'] ?? '' }} ({{ $m['currency'] ?? '' }})</li>
                        @endif
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
        <h3 class="text-lg font-semibold text-gray-900 mb-3">Transactions by type</h3>
        @if($txByType === [])
            <p class="text-sm text-gray-500">No transactions yet.</p>
        @else
            <div class="flex flex-wrap gap-2">
                @foreach($txByType as $type => $cnt)
                    <span class="inline-flex items-center bg-gray-100 rounded-full px-3 py-1 text-sm">
                        <span class="font-mono text-gray-700">{{ $type }}</span>
                        <span class="ml-2 font-semibold">{{ number_format($cnt) }}</span>
                    </span>
                @endforeach
            </div>
        @endif
    </div>

    <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Recent transactions</h3>
            <p class="text-sm text-gray-500">Latest 40 rows</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-600">
                    <tr>
                        <th class="px-4 py-2">ID</th>
                        <th class="px-4 py-2">When</th>
                        <th class="px-4 py-2">Type</th>
                        <th class="px-4 py-2">Amount</th>
                        <th class="px-4 py-2">Wallet</th>
                        <th class="px-4 py-2">Counterparty</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($recentTx as $t)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 font-mono text-xs">{{ $t->id }}</td>
                            <td class="px-4 py-2 whitespace-nowrap">{{ $t->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ $t->type }}</td>
                            <td class="px-4 py-2">{{ number_format((float) $t->amount, 2) }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ $t->wallet?->phone_e164 ?? '—' }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ $t->counterparty_phone_e164 ?? $t->counterparty_account_number ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No transactions.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @php
        if (is_array(old('pairs'))) {
            $pairRows = old('pairs');
        } else {
            $pairRows = [];
            foreach ($fxRates as $r) {
                $pairRows[] = [
                    'from_currency' => $r->from_currency,
                    'to_currency' => $r->to_currency,
                    'rate' => (string) $r->rate,
                ];
            }
            for ($u = 0; $u < 8; $u++) {
                $pairRows[] = ['from_currency' => '', 'to_currency' => '', 'rate' => ''];
            }
        }
    @endphp

    @php
        if (is_array(old('country_dial_extra'))) {
            $dialExtraFormRows = old('country_dial_extra');
        } else {
            $dialExtraFormRows = \App\Models\Setting::get('whatsapp_country_dial_extra', []) ?: [];
        }
        if (! is_array($dialExtraFormRows)) {
            $dialExtraFormRows = [];
        }
        while (count($dialExtraFormRows) < 8) {
            $dialExtraFormRows[] = ['dial' => '', 'country' => '', 'currency' => '', 'label' => ''];
        }
        if (is_array(old('instances_extra'))) {
            $instancesExtraFormRows = old('instances_extra');
        } else {
            $instancesExtraFormRows = [];
            $st = \App\Models\Setting::get('whatsapp_wallet_instances_extra', []);
            if (is_array($st)) {
                foreach ($st as $n => $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $f = $row['features'] ?? [];
                    if (! is_array($f)) {
                        $f = [];
                    }
                    $instancesExtraFormRows[] = [
                        'name' => $n,
                        'label' => $row['label'] ?? '',
                        'country' => $row['country'] ?? '',
                        'currency' => $row['currency'] ?? '',
                        'feature_p2p' => ! empty($f['p2p']),
                        'feature_bank' => ! empty($f['bank']),
                        'feature_vtu' => ! empty($f['vtu']),
                        'feature_rentals' => ! empty($f['rentals']),
                    ];
                }
            }
        }
        if (! is_array($instancesExtraFormRows)) {
            $instancesExtraFormRows = [];
        }
        while (count($instancesExtraFormRows) < 4) {
            $instancesExtraFormRows[] = [
                'name' => '', 'label' => '', 'country' => '', 'currency' => '',
                'feature_p2p' => true, 'feature_bank' => false, 'feature_vtu' => false, 'feature_rentals' => false,
            ];
        }
    @endphp

    <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">Integration settings</h3>
        <p class="text-sm text-gray-600 mb-4">Saved in the <code class="bg-gray-100 px-1 rounded">settings</code> table (group <code class="bg-gray-100 px-1 rounded">whatsapp</code>). Empty fields fall back to <code class="bg-gray-100 px-1 rounded">.env</code> where the app reads env directly.</p>

        <form action="{{ route('admin.whatsapp-wallet.update') }}" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="border border-indigo-100 bg-indigo-50/50 rounded-lg p-4 space-y-4">
                <div>
                    <h4 class="text-sm font-semibold text-gray-900">Add or override countries (dial → currency)</h4>
                    <p class="text-xs text-gray-600 mt-1">Base list comes from <code class="bg-white px-1 rounded">config/whatsapp_wallet_regions.php</code>. Rows here are <strong>merged</strong>: the same <code class="text-xs">dial</code> (e.g. 260) replaces the file entry. Use full E.164-style codes without +. For local-only number parsing, some regions may still need code changes — full international numbers work for wallet currency detection.</p>
                </div>
                <div class="overflow-x-auto border border-indigo-100 rounded-lg bg-white">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-left text-gray-700">
                            <tr>
                                <th class="px-3 py-2 font-medium">Dial (digits)</th>
                                <th class="px-3 py-2 font-medium">Country ISO2</th>
                                <th class="px-3 py-2 font-medium">Currency ISO3</th>
                                <th class="px-3 py-2 font-medium">Label</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($dialExtraFormRows as $i => $dr)
                                <tr>
                                    <td class="px-3 py-2">
                                        <input type="text" name="country_dial_extra[{{ $i }}][dial]" value="{{ $dr['dial'] ?? '' }}" inputmode="numeric" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm font-mono" placeholder="e.g. 260" maxlength="4">
                                    </td>
                                    <td class="px-3 py-2">
                                        <input type="text" name="country_dial_extra[{{ $i }}][country]" value="{{ $dr['country'] ?? '' }}" maxlength="2" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm font-mono uppercase" placeholder="ZM">
                                    </td>
                                    <td class="px-3 py-2">
                                        <input type="text" name="country_dial_extra[{{ $i }}][currency]" value="{{ $dr['currency'] ?? '' }}" maxlength="3" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm font-mono uppercase" placeholder="ZMW">
                                    </td>
                                    <td class="px-3 py-2">
                                        <input type="text" name="country_dial_extra[{{ $i }}][label]" value="{{ $dr['label'] ?? '' }}" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm" placeholder="Zambia">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div>
                    <h4 class="text-sm font-semibold text-gray-900">Extra Evolution instances (optional)</h4>
                    <p class="text-xs text-gray-600 mt-1">Instance <strong>name</strong> must match the name in your Evolution API. Merged with config; same name is overridden. Features match the wallet product toggles (e.g. P2P on, bank rails off for new markets).</p>
                </div>
                <div class="overflow-x-auto border border-indigo-100 rounded-lg bg-white text-xs">
                    <table class="min-w-full">
                        <thead class="bg-gray-50 text-left text-gray-700">
                            <tr>
                                <th class="px-2 py-2 font-medium">Instance name</th>
                                <th class="px-2 py-2 font-medium">Label</th>
                                <th class="px-2 py-2 font-medium">CC</th>
                                <th class="px-2 py-2 font-medium">Cur</th>
                                <th class="px-2 py-2 font-medium text-center">P2P</th>
                                <th class="px-2 py-2 font-medium text-center">Bank</th>
                                <th class="px-2 py-2 font-medium text-center">VTU</th>
                                <th class="px-2 py-2 font-medium text-center">Rent</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($instancesExtraFormRows as $i => $ir)
                                <tr>
                                    <td class="px-2 py-1.5 align-top">
                                        <input type="text" name="instances_extra[{{ $i }}][name]" value="{{ $ir['name'] ?? '' }}" class="w-full border border-gray-300 rounded px-1.5 py-1" placeholder="Zambia">
                                    </td>
                                    <td class="px-2 py-1.5 align-top">
                                        <input type="text" name="instances_extra[{{ $i }}][label]" value="{{ $ir['label'] ?? '' }}" class="w-full border border-gray-300 rounded px-1.5 py-1" placeholder="Zambia">
                                    </td>
                                    <td class="px-2 py-1.5 align-top">
                                        <input type="text" name="instances_extra[{{ $i }}][country]" value="{{ $ir['country'] ?? '' }}" maxlength="2" class="w-14 border border-gray-300 rounded px-1.5 py-1 font-mono uppercase" placeholder="ZM">
                                    </td>
                                    <td class="px-2 py-1.5 align-top">
                                        <input type="text" name="instances_extra[{{ $i }}][currency]" value="{{ $ir['currency'] ?? '' }}" maxlength="3" class="w-16 border border-gray-300 rounded px-1.5 py-1 font-mono uppercase" placeholder="ZMW">
                                    </td>
                                    <td class="px-2 py-1.5 text-center align-middle">
                                        <input type="hidden" name="instances_extra[{{ $i }}][feature_p2p]" value="0">
                                        <input type="checkbox" name="instances_extra[{{ $i }}][feature_p2p]" value="1" class="rounded border-gray-300" @checked(!empty($ir['feature_p2p']))>
                                    </td>
                                    <td class="px-2 py-1.5 text-center align-middle">
                                        <input type="hidden" name="instances_extra[{{ $i }}][feature_bank]" value="0">
                                        <input type="checkbox" name="instances_extra[{{ $i }}][feature_bank]" value="1" class="rounded border-gray-300" @checked(!empty($ir['feature_bank']))>
                                    </td>
                                    <td class="px-2 py-1.5 text-center align-middle">
                                        <input type="hidden" name="instances_extra[{{ $i }}][feature_vtu]" value="0">
                                        <input type="checkbox" name="instances_extra[{{ $i }}][feature_vtu]" value="1" class="rounded border-gray-300" @checked(!empty($ir['feature_vtu']))>
                                    </td>
                                    <td class="px-2 py-1.5 text-center align-middle">
                                        <input type="hidden" name="instances_extra[{{ $i }}][feature_rentals]" value="0">
                                        <input type="checkbox" name="instances_extra[{{ $i }}][feature_rentals]" value="1" class="rounded border-gray-300" @checked(!empty($ir['feature_rentals']))>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">App URL</label>
                    <input type="url" name="whatsapp_app_url" value="{{ old('whatsapp_app_url', $wa['whatsapp_app_url'] ?? env('WHATSAPP_APP_URL')) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Public URL (PIN pages)</label>
                    <input type="url" name="whatsapp_public_url" value="{{ old('whatsapp_public_url', $wa['whatsapp_public_url'] ?? '') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Webhook secret (override)</label>
                    <input type="password" name="whatsapp_webhook_secret" value="{{ old('whatsapp_webhook_secret', $wa['whatsapp_webhook_secret'] ?? '') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Evolution API base URL</label>
                    <input type="url" name="whatsapp_evolution_base_url" value="{{ old('whatsapp_evolution_base_url', $wa['whatsapp_evolution_base_url'] ?? env('WHATSAPP_EVOLUTION_BASE_URL')) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Evolution API key</label>
                    <input type="password" name="whatsapp_evolution_api_key" value="{{ old('whatsapp_evolution_api_key', $wa['whatsapp_evolution_api_key'] ?? env('WHATSAPP_EVOLUTION_API_KEY')) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Instance — default (NG)</label>
                    <input type="text" name="whatsapp_evolution_instance_default" value="{{ old('whatsapp_evolution_instance_default', $wa['whatsapp_evolution_instance_default'] ?? env('WHATSAPP_EVOLUTION_INSTANCE', 'Whatsapp')) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Instance — Namibia</label>
                    <input type="text" name="whatsapp_evolution_instance_namibia" value="{{ old('whatsapp_evolution_instance_namibia', $wa['whatsapp_evolution_instance_namibia'] ?? env('WHATSAPP_EVOLUTION_INSTANCE_NAMIBIA', 'Namibia')) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Instance — global (optional)</label>
                    <input type="text" name="whatsapp_evolution_instance_global" value="{{ old('whatsapp_evolution_instance_global', $wa['whatsapp_evolution_instance_global'] ?? env('WHATSAPP_EVOLUTION_INSTANCE_GLOBAL')) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tier 1 max balance</label>
                    <input type="number" step="0.01" name="whatsapp_wallet_tier1_max_balance" value="{{ old('whatsapp_wallet_tier1_max_balance', $wa['whatsapp_wallet_tier1_max_balance'] ?? env('WHATSAPP_WALLET_TIER1_MAX_BALANCE', 50000)) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tier 1 daily transfer</label>
                    <input type="number" step="0.01" name="whatsapp_wallet_tier1_daily_transfer" value="{{ old('whatsapp_wallet_tier1_daily_transfer', $wa['whatsapp_wallet_tier1_daily_transfer'] ?? env('WHATSAPP_WALLET_TIER1_DAILY_TRANSFER', 50000)) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">PIN / confirm link TTL (min)</label>
                    <input type="number" name="whatsapp_transfer_confirm_ttl_minutes" value="{{ old('whatsapp_transfer_confirm_ttl_minutes', $wa['whatsapp_transfer_confirm_ttl_minutes'] ?? env('WHATSAPP_WALLET_TRANSFER_CONFIRM_TTL_MINUTES', 15)) }}" min="5" max="1440" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
            </div>

            <div class="border-t border-gray-200 pt-4">
                <h4 class="text-sm font-semibold text-gray-800 mb-2">Cross-border P2P (messaging)</h4>
                <label class="inline-flex items-center gap-2 mb-3">
                    <input type="checkbox" name="whatsapp_cross_border_p2p_enabled" value="1" class="rounded border-gray-300" @checked(old('whatsapp_cross_border_p2p_enabled', $wa['whatsapp_cross_border_p2p_enabled'] ?? false))>
                    <span class="text-sm text-gray-700">Enable cross-border sends (FX from live API and/or manual table below)</span>
                </label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">FX rate source</label>
                        <select name="whatsapp_cross_border_fx_rate_source" class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg">
                            @php $fxSrc = old('whatsapp_cross_border_fx_rate_source', $wa['whatsapp_cross_border_fx_rate_source'] ?? 'manual'); @endphp
                            <option value="manual" @selected($fxSrc === 'manual')>Manual — use table below (and legacy JSON if table empty)</option>
                            <option value="open_er_usd" @selected($fxSrc === 'open_er_usd')>Live — USD spot via open.er-api.com (ExchangeRate-API; no key), fallback to table if a currency is missing</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Live quotes are cached ~1h. Terms: <a href="https://www.exchangerate-api.com/terms" class="text-indigo-600 underline" target="_blank" rel="noopener">exchangerate-api.com/terms</a></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Global FX profit margin (%)</label>
                        <input type="number" step="0.01" min="0" max="99.99" name="whatsapp_cross_border_fx_profit_margin_percent" value="{{ old('whatsapp_cross_border_fx_profit_margin_percent', $wa['whatsapp_cross_border_fx_profit_margin_percent'] ?? 0) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <p class="text-xs text-gray-500 mt-1">Recipient receives <strong>(100 − margin)%</strong> of the converted amount; same rule for manual and live rates. Use 0 for no markup.</p>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Conversion prompt template</label>
                    <textarea name="whatsapp_cross_border_prompt_template" rows="3" class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg" placeholder="You pay {debit} — they get {credit} (FX). Optional: {sender_currency} {recipient_currency} {debit_amount} {credit_amount}">{{ old('whatsapp_cross_border_prompt_template', \App\Models\Setting::get('whatsapp_cross_border_prompt_template') ?? '') }}</textarea>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Message if cross-border disabled</label>
                        <textarea name="whatsapp_cross_border_disabled_message" rows="2" class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg">{{ old('whatsapp_cross_border_disabled_message', \App\Models\Setting::get('whatsapp_cross_border_disabled_message') ?? '') }}</textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Message if FX pair missing</label>
                        <textarea name="whatsapp_cross_border_missing_rate_message" rows="2" class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg">{{ old('whatsapp_cross_border_missing_rate_message', \App\Models\Setting::get('whatsapp_cross_border_missing_rate_message') ?? '') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700">
                    <i class="fas fa-save mr-2"></i>Save WhatsApp settings
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">Cross-border FX rates (manual)</h3>
        <p class="text-sm text-gray-600 mb-1">Each row: <strong>1 unit of “From”</strong> converts to <strong>rate × units of “To”</strong> (e.g. NGN → USD, rate = USD per 1 NGN). Reverse is derived when needed. With <strong>Live</strong> source, this table is only used when the API does not list a currency or the feed fails.</p>
        @if($legacyFxPairCount > 0 && $fxRates->isEmpty())
            <p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-4">
                You still have <strong>{{ $legacyFxPairCount }}</strong> pair(s) in the old JSON setting. They are used only while this table is empty. Save rates here to switch to the database (you can copy values from your backup).
            </p>
        @endif
        @error('fx_rates')
            <div class="text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2 mb-4">{{ $message }}</div>
        @enderror

        <form action="{{ route('admin.whatsapp-wallet.fx-rates.update') }}" method="POST" class="space-y-4">
            @csrf
            @method('PUT')
            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-700">
                        <tr>
                            <th class="px-3 py-2 font-medium">From</th>
                            <th class="px-3 py-2 font-medium">To</th>
                            <th class="px-3 py-2 font-medium">Rate (To per 1 From)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($pairRows as $i => $p)
                            @php
                                $fromVal = is_array($p) ? ($p['from_currency'] ?? '') : '';
                                $toVal = is_array($p) ? ($p['to_currency'] ?? '') : '';
                                $rateVal = is_array($p) ? ($p['rate'] ?? '') : '';
                                $dlIdFrom = 'fx-dl-from-'.$i;
                                $dlIdTo = 'fx-dl-to-'.$i;
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 align-middle">
                                    <input type="text" name="pairs[{{ $i }}][from_currency]" value="{{ $fromVal }}" maxlength="3" autocomplete="off" list="{{ $dlIdFrom }}" placeholder="NGN" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm font-mono uppercase">
                                    <datalist id="{{ $dlIdFrom }}">
                                        @foreach($fxCurrencyCodes as $code)
                                            <option value="{{ $code }}">
                                        @endforeach
                                    </datalist>
                                </td>
                                <td class="px-3 py-2 align-middle">
                                    <input type="text" name="pairs[{{ $i }}][to_currency]" value="{{ $toVal }}" maxlength="3" autocomplete="off" list="{{ $dlIdTo }}" placeholder="USD" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm font-mono uppercase">
                                    <datalist id="{{ $dlIdTo }}">
                                        @foreach($fxCurrencyCodes as $code)
                                            <option value="{{ $code }}">
                                        @endforeach
                                    </datalist>
                                </td>
                                <td class="px-3 py-2 align-middle">
                                    <input type="text" inputmode="decimal" name="pairs[{{ $i }}][rate]" value="{{ $rateVal }}" placeholder="e.g. 0.00062" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm font-mono">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-gray-500">Leave a row blank (both currencies empty) to skip it. Saving replaces all stored pairs with the non-empty rows below.</p>
            <div class="flex justify-end">
                <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700">
                    <i class="fas fa-exchange-alt mr-2"></i>Save FX rates
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
