@extends('layouts.admin')

@section('title', 'Wallet '.$wallet->phone_e164)
@section('page-title', 'Wallet user')

@section('content')
@php
    $bucketBadge = fn (string $bucket): string => match ($bucket) {
        'failed' => 'bg-red-100 text-red-800',
        'pending' => 'bg-amber-100 text-amber-800',
        'successful' => 'bg-green-100 text-green-800',
        default => 'bg-gray-100 text-gray-700',
    };
@endphp
<div class="space-y-6">
    @include('admin.whatsapp-wallet.partials.nav')

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">{{ session('error') }}</div>
    @endif

    <div class="flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.whatsapp-wallet.wallets.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
            <i class="fas fa-arrow-left mr-1"></i> All wallet users
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-lg border border-gray-200 p-6 shadow-sm space-y-4">
            <div class="flex flex-wrap justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 font-mono">{{ $wallet->phone_e164 }}</h2>
                    <p class="text-gray-600 mt-1">{{ $wallet->displayName() ?? 'No display name' }}</p>
                    @if($wallet->pay_code)
                        <p class="text-sm text-gray-500 mt-1">Pay code: <span class="font-mono font-medium">{{ $wallet->pay_code }}</span></p>
                    @endif
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-500">Personal balance</p>
                    <p class="text-3xl font-bold text-gray-900">₦{{ number_format((float) $wallet->balance, 2) }}</p>
                    <p class="text-sm text-gray-500 mt-2">Business balance</p>
                    <p class="text-xl font-bold text-cyan-700">₦{{ number_format((float) $wallet->business_balance, 2) }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.whatsapp-wallet.wallets.link-business', $wallet) }}" class="border border-gray-100 rounded-lg p-4 bg-gray-50 space-y-3">
                @csrf
                @method('PUT')
                <p class="text-sm font-semibold text-gray-800">Link merchant business</p>
                <p class="text-xs text-gray-500">Connect this WhatsApp wallet to a CheckoutPay business account for a separate business ledger in the app.</p>
                <select name="linked_business_id" class="w-full rounded-lg border-gray-300 text-sm">
                    <option value="">— No linked business —</option>
                    @foreach($linkableBusinesses as $biz)
                        <option value="{{ $biz->id }}" @selected($wallet->linked_business_id === $biz->id)>
                            #{{ $biz->id }} · {{ $biz->name }} ({{ $biz->email }})
                        </option>
                    @endforeach
                </select>
                @if($wallet->linkedBusiness)
                    <p class="text-xs text-gray-600">Linked: <strong>{{ $wallet->linkedBusiness->name }}</strong></p>
                @endif
                <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">Save link</button>
            </form>

            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                <div><dt class="text-gray-500">Wallet ID</dt><dd class="font-mono">#{{ $wallet->id }}</dd></div>
                <div><dt class="text-gray-500">Tier</dt><dd>{{ $wallet->isTier2() ? 'Tier 2 (Rubies VA)' : 'Tier 1' }}</dd></div>
                <div><dt class="text-gray-500">Status</dt>
                    <dd>
                        @if($wallet->isActive())
                            <span class="text-green-700 font-medium">Active</span>
                        @else
                            <span class="text-red-700 font-medium">Suspended</span>
                        @endif
                    </dd>
                </div>
                <div><dt class="text-gray-500">Bot replies</dt>
                    <dd>
                        @if($wallet->isAdminBotPaused())
                            <span class="text-amber-800 font-medium">Manual chat (paused)</span>
                        @else
                            <span class="text-green-700">Automated</span>
                        @endif
                    </dd>
                </div>
                <div><dt class="text-gray-500">PIN</dt><dd>{{ $wallet->hasPin() ? 'Configured' : 'Not set' }}</dd></div>
                <div><dt class="text-gray-500">Created</dt><dd>{{ $wallet->created_at?->format('M j, Y H:i') ?? '—' }}</dd></div>
                @if($wallet->isTier1())
                    <div class="sm:col-span-2 border-t border-gray-100 pt-3 mt-1">
                        <p class="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-2">Tier 1 limits (today)</p>
                    </div>
                    <div>
                        <dt class="text-gray-500">Max wallet balance</dt>
                        <dd>₦{{ number_format($wallet->tier1MaxBalance(), 2) }}
                            <span class="text-gray-500">(₦{{ number_format($wallet->tier1BalanceHeadroom(), 2) }} headroom)</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Daily send limit (out only)</dt>
                        <dd class="font-medium">₦{{ number_format($wallet->tier1DailyOutLimit(), 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Sent out today</dt>
                        <dd>₦{{ number_format($wallet->tier1DailyOutUsed(), 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Send remaining today</dt>
                        <dd class="{{ $wallet->tier1DailyOutRemaining() < 1 ? 'text-red-700 font-semibold' : 'text-green-700 font-semibold' }}">
                            ₦{{ number_format($wallet->tier1DailyOutRemaining(), 2) }}
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <p class="text-xs text-gray-500">Top-ups and money received do not count toward the daily send limit — only outbound transfers (P2P, bank, airtime/VTU, partner debits).</p>
                    </div>
                @else
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500">Tier 1 daily send tracking</dt>
                        <dd class="text-gray-600">Not applicable — Tier 2 has no Tier 1 send cap.</dd>
                    </div>
                @endif
                @if($wallet->mevon_virtual_account_number)
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500">Pay-in VA</dt>
                        <dd class="font-mono">{{ $wallet->mevon_bank_name ?? 'Bank' }} · {{ $wallet->mevon_virtual_account_number }}</dd>
                    </div>
                @endif
            </dl>

            <div class="flex flex-wrap gap-2 pt-2 border-t border-gray-100">
                <a href="{{ route('admin.whatsapp-wallet.transactions.index', ['wallet_id' => $wallet->id]) }}"
                   class="text-sm bg-gray-100 hover:bg-gray-200 px-3 py-2 rounded-lg">All transactions</a>
                <a href="{{ route('admin.whatsapp-wallet.transactions.p2p', ['wallet_id' => $wallet->id]) }}"
                   class="text-sm bg-gray-100 hover:bg-gray-200 px-3 py-2 rounded-lg">P2P only</a>
                <a href="{{ route('admin.whatsapp-wallet.transactions.index', ['wallet_id' => $wallet->id, 'type' => \App\Models\WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT]) }}"
                   class="text-sm bg-gray-100 hover:bg-gray-200 px-3 py-2 rounded-lg">Bank transfers</a>
            </div>
        </div>

        <div class="space-y-4">
            <div class="bg-white rounded-lg border border-gray-200 p-5 shadow-sm">
                <h3 class="font-semibold text-gray-900 mb-3">Activity summary</h3>
                <ul class="text-sm space-y-2">
                    <li class="flex justify-between"><span>Bank transfers</span><span class="font-medium">{{ number_format($wallet->bank_transfers_count ?? 0) }}</span></li>
                    <li class="flex justify-between"><span>P2P legs</span><span class="font-medium">{{ number_format($wallet->p2p_count ?? 0) }}</span></li>
                    <li class="flex justify-between"><span>Top-ups</span><span class="font-medium">{{ number_format($wallet->topups_count ?? 0) }}</span></li>
                    @if(($pendingPayouts ?? 0) > 0)
                        <li class="flex justify-between text-amber-800">
                            <span>Pending payouts (48h)</span>
                            <a href="{{ route('admin.whatsapp-wallet.transactions.pending', ['search' => $wallet->phone_e164]) }}" class="font-bold hover:underline">{{ $pendingPayouts }}</a>
                        </li>
                    @endif
                </ul>
            </div>

            <div class="bg-white rounded-lg border border-gray-200 p-5 shadow-sm">
                <h3 class="font-semibold text-gray-900 mb-3">Account controls</h3>
                <form method="POST" action="{{ route('admin.whatsapp-wallet.wallets.status', $wallet) }}" class="space-y-3">
                    @csrf
                    @method('PUT')
                    @if($wallet->isActive())
                        <input type="hidden" name="status" value="suspended">
                        <button type="submit" class="w-full bg-red-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-red-700"
                            onclick="return confirm('Suspend this wallet? User cannot spend until reactivated.')">
                            Suspend wallet
                        </button>
                    @else
                        <input type="hidden" name="status" value="active">
                        <button type="submit" class="w-full bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">
                            Reactivate wallet
                        </button>
                    @endif
                </form>
            </div>

            <div class="bg-white rounded-lg border border-gray-200 p-5 shadow-sm">
                <h3 class="font-semibold text-gray-900 mb-2">App push notification</h3>
                <p class="text-sm text-gray-600 mb-3">
                    Send a Firebase (FCM) alert to this user&apos;s CheckoutNow app.
                </p>
                <dl class="text-xs text-gray-600 space-y-1 mb-4">
                    <div class="flex justify-between gap-2">
                        <dt>CheckoutNow FCM project</dt>
                        <dd class="font-mono text-xs">{{ $pushStatus['fcm_project_id'] ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt>CheckoutNow service account</dt>
                        <dd class="font-mono text-xs {{ ($pushStatus['projects_match'] ?? false) ? 'text-green-700' : 'text-red-700' }}">
                            {{ $pushStatus['service_account_project_id'] ?? '—' }}
                            @if(!($pushStatus['projects_match'] ?? true) && ($pushStatus['fcm_project_id'] ?? '') !== '')
                                <span class="block text-red-600 font-normal">Upload service account from checkout-now-a2b2f (CHECKOUTNOW_FCM_SERVICE_ACCOUNT_JSON)</span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt>CheckoutNow push configured</dt>
                        <dd class="{{ ($pushStatus['configured'] ?? false) ? 'text-green-700 font-medium' : 'text-red-700 font-medium' }}">
                            {{ ($pushStatus['configured'] ?? false) ? 'Yes' : 'No' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt>Device token</dt>
                        <dd class="{{ ($pushStatus['has_token'] ?? false) ? 'text-green-700 font-medium' : 'text-amber-700 font-medium' }}">
                            {{ ($pushStatus['has_token'] ?? false) ? 'Registered' : 'None' }}
                        </dd>
                    </div>
                    @if(($pushStatus['has_token'] ?? false))
                        <div class="flex justify-between gap-2">
                            <dt>Platform</dt>
                            <dd class="capitalize">{{ $pushStatus['platform'] ?? '—' }}</dd>
                        </div>
                        @if(!empty($pushStatus['updated_at']))
                            <div class="flex justify-between gap-2">
                                <dt>Token updated</dt>
                                <dd>{{ \Illuminate\Support\Carbon::parse($pushStatus['updated_at'])->diffForHumans() }}</dd>
                            </div>
                        @endif
                    @endif
                </dl>
                <form method="POST" action="{{ route('admin.whatsapp-wallet.wallets.push', $wallet) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Title</label>
                        <input type="text" name="title" value="{{ old('title', 'CheckoutNow') }}" maxlength="120" required
                            class="w-full rounded-lg border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Message</label>
                        <textarea name="body" rows="3" maxlength="500" required
                            class="w-full rounded-lg border-gray-300 text-sm"
                            placeholder="Short message the user will see on their phone">{{ old('body') }}</textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Open screen (optional)</label>
                        <select name="screen" class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="">Default</option>
                            <option value="home" @selected(old('screen') === 'home')>Home</option>
                            <option value="history" @selected(old('screen') === 'history')>Transaction history</option>
                            <option value="saving" @selected(old('screen') === 'saving')>Savings</option>
                            <option value="card" @selected(old('screen') === 'card')>Virtual card</option>
                            <option value="profile" @selected(old('screen') === 'profile')>Profile</option>
                            <option value="support" @selected(old('screen') === 'support')>Support</option>
                        </select>
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-indigo-700 disabled:opacity-50"
                        @disabled(!($pushStatus['configured'] ?? false) || !($pushStatus['has_token'] ?? false))
                        onclick="return confirm('Send this push notification to {{ $wallet->phone_e164 }}?')">
                        <i class="fas fa-bell mr-1"></i> Send push
                    </button>
                    @if(!($pushStatus['configured'] ?? false))
                        <p class="text-xs text-red-700">Set <code class="bg-red-50 px-1 rounded">CHECKOUTNOW_FCM_PROJECT_ID</code> and <code class="bg-red-50 px-1 rounded">CHECKOUTNOW_FCM_SERVICE_ACCOUNT_JSON</code> in server <code class="bg-red-50 px-1 rounded">.env</code> (service account from Firebase project checkout-now-a2b2f).</p>
                    @elseif(!($pushStatus['has_token'] ?? false))
                        <p class="text-xs text-amber-800">User must sign in on the mobile app and allow notifications.</p>
                    @endif
                </form>
            </div>

            <div class="bg-white rounded-lg border border-gray-200 p-5 shadow-sm">
                <h3 class="font-semibold text-gray-900 mb-2">Manual chat mode</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Pause automated bot replies so you can message this user directly on WhatsApp.
                    The bot stays silent until the user sends <span class="font-mono font-medium">START BOT</span>
                    or you resume it here.
                </p>
                @if($wallet->isAdminBotPaused())
                    <p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-3">
                        Bot is paused for this user. You can chat manually from your WhatsApp inbox.
                    </p>
                @endif
                <form method="POST" action="{{ route('admin.whatsapp-wallet.wallets.bot-pause', $wallet) }}">
                    @csrf
                    @method('PUT')
                    @if($wallet->isAdminBotPaused())
                        <input type="hidden" name="admin_bot_paused" value="0">
                        <button type="submit" class="w-full bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">
                            Resume automated bot
                        </button>
                    @else
                        <input type="hidden" name="admin_bot_paused" value="1">
                        <button type="submit" class="w-full bg-amber-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-amber-700"
                            onclick="return confirm('Pause bot auto-replies for this user? You can chat manually until they send START BOT or you resume here.')">
                            Pause bot (manual chat)
                        </button>
                    @endif
                </form>
            </div>
        </div>
    </div>

    @if(($businessNameRegistrations ?? collect())->isNotEmpty())
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Business name registrations</h3>
                    @if(($businessNamePendingCount ?? 0) > 0)
                        <p class="text-sm text-amber-800 mt-1">{{ $businessNamePendingCount }} pending review</p>
                    @endif
                </div>
                <a href="{{ route('admin.business-name-registrations.index', ['wallet_id' => $wallet->id]) }}"
                   class="text-sm text-primary hover:underline">View all</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-gray-600">Reference</th>
                            <th class="px-4 py-2 text-left text-gray-600">Proposed name</th>
                            <th class="px-4 py-2 text-left text-gray-600">Status</th>
                            <th class="px-4 py-2 text-left text-gray-600">Progress</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($businessNameRegistrations as $bnr)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-mono text-xs">{{ $bnr->reference }}</td>
                                <td class="px-4 py-2">{{ $bnr->proposed_name }}</td>
                                <td class="px-4 py-2">{{ $bnr->statusDisplayLabel() }}</td>
                                <td class="px-4 py-2">{{ (int) $bnr->progress_percent }}%</td>
                                <td class="px-4 py-2 text-right">
                                    <a href="{{ route('admin.business-name-registrations.show', $bnr) }}" class="text-primary hover:underline">Review</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Recent transactions</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-gray-600">ID</th>
                        <th class="px-4 py-2 text-left text-gray-600">Date</th>
                        <th class="px-4 py-2 text-left text-gray-600">Type</th>
                        <th class="px-4 py-2 text-right text-gray-600">Amount</th>
                        <th class="px-4 py-2 text-left text-gray-600">Counterparty</th>
                        <th class="px-4 py-2 text-left text-gray-600">Payout</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($recentTx as $txn)
                        @php $bucket = $txn->payoutBucketLabel(); @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2">#{{ $txn->id }}</td>
                            <td class="px-4 py-2 whitespace-nowrap text-gray-600">{{ $txn->created_at?->format('M j, H:i') }}</td>
                            <td class="px-4 py-2">{{ str_replace('_', ' ', $txn->type) }}</td>
                            <td class="px-4 py-2 text-right font-medium">₦{{ number_format((float) $txn->amount, 2) }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ $txn->counterparty_phone_e164 ?? $txn->counterparty_account_name ?? $txn->counterparty_account_number ?? '—' }}</td>
                            <td class="px-4 py-2">
                                @if($txn->type === \App\Models\WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT)
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs {{ $bucketBadge($bucket) }}">{{ ucfirst($bucket) }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right">
                                <a href="{{ route('admin.whatsapp-wallet.transactions.show', $txn) }}" class="text-primary hover:underline">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">No transactions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
