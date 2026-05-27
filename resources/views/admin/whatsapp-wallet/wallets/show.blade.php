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
                    <p class="text-sm text-gray-500">Balance</p>
                    <p class="text-3xl font-bold text-gray-900">₦{{ number_format((float) $wallet->balance, 2) }}</p>
                </div>
            </div>

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
                <div><dt class="text-gray-500">Daily transfer today</dt><dd>₦{{ number_format((float) $wallet->daily_transfer_total, 2) }}</dd></div>
                <div><dt class="text-gray-500">Created</dt><dd>{{ $wallet->created_at?->format('M j, Y H:i') ?? '—' }}</dd></div>
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
