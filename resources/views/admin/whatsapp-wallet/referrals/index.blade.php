@extends('layouts.admin')

@section('title', $pageTitle ?? 'Referrals')
@section('page-title', $pageTitle ?? 'Referrals')

@section('content')
<div class="space-y-6">
    @include('admin.whatsapp-wallet.partials.nav')

    <div>
        <h3 class="text-lg font-semibold text-gray-900">{{ $pageTitle }}</h3>
        <p class="text-sm text-gray-600 mt-1">{{ $pageSubtitle }}</p>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 text-green-800 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 text-red-800 px-4 py-3 text-sm">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <p class="text-xs text-gray-500">Attributions</p>
            <p class="text-2xl font-semibold text-gray-900">{{ number_format($stats['attributions']) }}</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <p class="text-xs text-gray-500">Active windows</p>
            <p class="text-2xl font-semibold text-gray-900">{{ number_format($stats['active_windows']) }}</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <p class="text-xs text-gray-500">Bonuses paid</p>
            <p class="text-2xl font-semibold text-gray-900">{{ number_format($stats['bonuses_paid_count']) }}</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <p class="text-xs text-gray-500">Bonus total (NGN)</p>
            <p class="text-2xl font-semibold text-gray-900">₦{{ number_format($stats['bonuses_paid_sum'], 2) }}</p>
        </div>
    </div>

    @if(auth('admin')->user()?->canManageSettings())
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
        <h4 class="font-semibold text-gray-900 mb-1">Launch announcement</h4>
        <p class="text-xs text-gray-500 mb-4">
            Email and/or app push telling wallet users we now have a referral programme. Message directs them to
            <strong>Profile → Refer and Earn</strong> for their code. Each wallet is marked once (<code>referral_launch_notified_at</code>); use force to resend.
        </p>
        <form method="POST" action="{{ route('admin.whatsapp-wallet.referrals.launch-reach') }}" class="mb-4">
            @csrf
            <button type="submit" class="text-sm px-3 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Estimate pending reach</button>
        </form>
        <form method="POST" action="{{ route('admin.whatsapp-wallet.referrals.notify-launch') }}" class="space-y-4">
            @csrf
            <div class="flex flex-wrap gap-4 text-sm text-gray-800">
                <label class="flex items-center gap-2">
                    <input type="hidden" name="channel_email" value="0">
                    <input type="checkbox" name="channel_email" value="1" checked class="rounded border-gray-300"> Email
                </label>
                <label class="flex items-center gap-2">
                    <input type="hidden" name="channel_push" value="0">
                    <input type="checkbox" name="channel_push" value="1" checked class="rounded border-gray-300"> App push
                </label>
                <label class="flex items-center gap-2">
                    <input type="hidden" name="dry_run" value="0">
                    <input type="checkbox" name="dry_run" value="1" class="rounded border-gray-300"> Dry run (no send)
                </label>
                <label class="flex items-center gap-2">
                    <input type="hidden" name="force" value="0">
                    <input type="checkbox" name="force" value="1" class="rounded border-gray-300"> Force (include already notified)
                </label>
            </div>
            <button type="submit" class="text-sm px-4 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700" onclick="return confirm('Send referral launch notifications?');">
                Send launch notifications
            </button>
        </form>
    </div>
    @endif

    @if(auth('admin')->user()?->canManageSettings())
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
        <h4 class="font-semibold text-gray-900 mb-3">Programme settings</h4>
        <p class="text-xs text-gray-500 mb-4">All commercial numbers are editable here — services never hardcode rates.</p>
        <form method="POST" action="{{ route('admin.whatsapp-wallet.referrals.settings') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @csrf
            @method('PUT')
            <label class="flex items-center gap-2 text-sm text-gray-800 col-span-full">
                <input type="hidden" name="referral_enabled" value="0">
                <input type="checkbox" name="referral_enabled" value="1" @checked($settings['enabled'] ?? false) class="rounded border-gray-300">
                Referrals enabled (master switch)
            </label>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Bonus months</label>
                <input type="number" min="1" max="60" name="referral_bonus_months" value="{{ old('referral_bonus_months', $settings['bonus_months'] ?? 6) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                @error('referral_bonus_months')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">First deposit %</label>
                <input type="number" step="0.01" min="0" max="100" name="referral_first_deposit_percent" value="{{ old('referral_first_deposit_percent', $settings['first_deposit_percent'] ?? 5) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                @error('referral_first_deposit_percent')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">First deposit max NGN (empty = no cap)</label>
                <input type="number" step="0.01" min="0" name="referral_first_deposit_max_ngn" value="{{ old('referral_first_deposit_max_ngn', $settings['first_deposit_max_ngn'] ?? '') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">First deposit min top-up NGN</label>
                <input type="number" step="0.01" min="0" name="referral_first_deposit_min_ngn" value="{{ old('referral_first_deposit_min_ngn', $settings['first_deposit_min_ngn'] ?? 0) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Milestone every (txs)</label>
                <input type="number" min="1" name="referral_milestone_every" value="{{ old('referral_milestone_every', $settings['milestone_every'] ?? 100) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Milestone amount NGN</label>
                <input type="number" step="0.01" min="0" name="referral_milestone_amount_ngn" value="{{ old('referral_milestone_amount_ngn', $settings['milestone_amount_ngn'] ?? 200) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-800">
                <input type="hidden" name="referral_leaderboard_enabled" value="0">
                <input type="checkbox" name="referral_leaderboard_enabled" value="1" @checked($settings['leaderboard_enabled'] ?? false) class="rounded border-gray-300">
                Leaderboard enabled
            </label>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Leaderboard pot NGN / month</label>
                <input type="number" step="0.01" min="0" name="referral_leaderboard_month_pot_ngn" value="{{ old('referral_leaderboard_month_pot_ngn', $settings['leaderboard_month_pot_ngn'] ?? 0) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Leaderboard top N</label>
                <input type="number" min="1" max="100" name="referral_leaderboard_top_n" value="{{ old('referral_leaderboard_top_n', $settings['leaderboard_top_n'] ?? 10) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-gray-700 mb-1">Leaderboard split</label>
                <input type="text" name="referral_leaderboard_split" value="{{ old('referral_leaderboard_split', is_array($settings['leaderboard_split'] ?? null) ? json_encode($settings['leaderboard_split']) : ($settings['leaderboard_split'] ?? 'equal')) }}" placeholder="equal or [50,30,20]" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <p class="text-xs text-gray-500 mt-1">Use <code>equal</code> or a JSON array of percentages for top ranks.</p>
            </div>
            <div class="col-span-full">
                <button type="submit" class="bg-green-700 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-800">Save settings</button>
            </div>
        </form>
    </div>
    @endif

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 font-semibold text-gray-900 text-sm">This month leaderboard (preview)</div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-gray-600">Rank</th>
                        <th class="px-4 py-2 text-left text-gray-600">Referrer</th>
                        <th class="px-4 py-2 text-left text-gray-600">Phone</th>
                        <th class="px-4 py-2 text-left text-gray-600">Score (counted txs)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($monthStandings as $i => $row)
                        <tr>
                            <td class="px-4 py-2">{{ $i + 1 }}</td>
                            <td class="px-4 py-2">{{ $row['display_name'] }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ $row['masked_phone'] }}</td>
                            <td class="px-4 py-2">{{ number_format($row['score']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">No activity yet this month.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 font-semibold text-gray-900 text-sm">Recent attributions</div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-gray-600">ID</th>
                        <th class="px-4 py-2 text-left text-gray-600">Referrer</th>
                        <th class="px-4 py-2 text-left text-gray-600">Referred</th>
                        <th class="px-4 py-2 text-left text-gray-600">Source</th>
                        <th class="px-4 py-2 text-left text-gray-600">Tx count</th>
                        <th class="px-4 py-2 text-left text-gray-600">Ends</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($referrals as $r)
                        <tr>
                            <td class="px-4 py-2">{{ $r->id }}</td>
                            <td class="px-4 py-2">
                                <a class="text-green-700 hover:underline" href="{{ route('admin.whatsapp-wallet.wallets.show', $r->referrer_wallet_id) }}">
                                    {{ $r->referrerWallet?->phone_e164 ?? $r->referrer_wallet_id }}
                                </a>
                            </td>
                            <td class="px-4 py-2">
                                <a class="text-green-700 hover:underline" href="{{ route('admin.whatsapp-wallet.wallets.show', $r->referred_wallet_id) }}">
                                    {{ $r->referredWallet?->phone_e164 ?? $r->referred_wallet_id }}
                                </a>
                            </td>
                            <td class="px-4 py-2">{{ $r->attribution_source }}</td>
                            <td class="px-4 py-2">{{ $r->counted_tx_total }}</td>
                            <td class="px-4 py-2 text-xs">{{ $r->bonus_ends_at?->format('Y-m-d') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">No referrals yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3">{{ $referrals->links() }}</div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 font-semibold text-gray-900 text-sm">Recent bonuses</div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-gray-600">ID</th>
                        <th class="px-4 py-2 text-left text-gray-600">Type</th>
                        <th class="px-4 py-2 text-left text-gray-600">Amount</th>
                        <th class="px-4 py-2 text-left text-gray-600">Referrer</th>
                        <th class="px-4 py-2 text-left text-gray-600">When</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($bonuses as $b)
                        <tr>
                            <td class="px-4 py-2">{{ $b->id }}</td>
                            <td class="px-4 py-2">{{ $b->type }}</td>
                            <td class="px-4 py-2">₦{{ number_format((float) $b->amount, 2) }}</td>
                            <td class="px-4 py-2">
                                <a class="text-green-700 hover:underline" href="{{ route('admin.whatsapp-wallet.wallets.show', $b->referrer_wallet_id) }}">#{{ $b->referrer_wallet_id }}</a>
                            </td>
                            <td class="px-4 py-2 text-xs">{{ $b->created_at?->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">No bonuses paid yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3">{{ $bonuses->links() }}</div>
    </div>
</div>
@endsection
