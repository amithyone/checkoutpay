@extends('layouts.admin')

@section('title', 'Card Management')
@section('page-title', 'Card Management')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Card Management</h2>
            <p class="text-sm text-gray-600 mt-1">Dollar Virtual Card requests from CheckoutNow — review, activate, retry, or refund fees</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <form method="POST" action="{{ route('admin.virtual-cards.refresh-rates') }}">
                @csrf
                <button type="submit" class="text-sm text-blue-700 hover:underline font-medium">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh app FX rates
                </button>
            </form>
            <a href="{{ route('admin.virtual-cards.stats') }}" class="text-sm text-emerald-700 hover:underline font-medium">
                <i class="fas fa-chart-line mr-1"></i> Profit statistics
            </a>
            <a href="{{ route('admin.virtual-cards.logs') }}" class="text-sm text-indigo-700 hover:underline font-medium">
                <i class="fas fa-list-alt mr-1"></i> Request &amp; webhook logs
            </a>
            <a href="{{ route('admin.settings.index') }}#vtu-virtual-card" class="text-sm text-primary hover:underline">
                <i class="fas fa-cog mr-1"></i> Card settings (enable &amp; fee)
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg border border-blue-200 p-4 shadow-sm bg-blue-50/40">
            <p class="text-xs text-gray-500 uppercase">App sell rate</p>
            <p class="text-2xl font-bold text-blue-800">
                @if(($publishedRates['sell_rate'] ?? null) !== null)
                    ₦{{ number_format($publishedRates['sell_rate'], 2) }}
                @else
                    —
                @endif
            </p>
            <p class="text-xs text-gray-500 mt-1">CheckoutNow fund / request fee</p>
        </div>
        <div class="bg-white rounded-lg border border-violet-200 p-4 shadow-sm bg-violet-50/40">
            <p class="text-xs text-gray-500 uppercase">App buy rate</p>
            <p class="text-2xl font-bold text-violet-800">
                @if(($publishedRates['buy_rate'] ?? null) !== null)
                    ₦{{ number_format($publishedRates['buy_rate'], 2) }}
                @else
                    —
                @endif
            </p>
            <p class="text-xs text-gray-500 mt-1">CheckoutNow withdraw</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm md:col-span-2">
            <p class="text-xs text-gray-500 uppercase">Published FX for app</p>
            <p class="text-sm text-gray-800 mt-1">
                Mid:
                @if(($publishedRates['mid'] ?? null) !== null)
                    <strong>₦{{ number_format($publishedRates['mid'], 2) }}</strong>
                @else
                    <strong>—</strong>
                @endif
                · Source: <strong>{{ $publishedRates['source'] ?? 'not published' }}</strong>
            </p>
            <p class="text-xs text-gray-500 mt-2">
                @if(!empty($publishedRates['published_at']))
                    Last synced {{ \Carbon\Carbon::parse($publishedRates['published_at'])->diffForHumans() }}
                    ({{ \Carbon\Carbon::parse($publishedRates['published_at'])->format('M j, Y g:i A') }})
                @else
                    Rates not published yet — use Refresh app FX rates.
                @endif
                · App reads settings only (no live Mevon per user).
            </p>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-gradient-to-br from-emerald-50 to-green-50 rounded-lg border-2 border-emerald-200 p-4">
            <p class="text-xs text-gray-600 uppercase">Total FX profit</p>
            <p class="text-2xl font-bold text-gray-900">₦{{ number_format($profitSummary['total_profit_ngn'], 2) }}</p>
            <a href="{{ route('admin.virtual-cards.stats') }}" class="text-xs text-emerald-700 hover:underline mt-2 inline-block">Full statistics →</a>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 uppercase">Request fee profit</p>
            <p class="text-xl font-bold text-indigo-700">₦{{ number_format($profitSummary['request_profit_ngn'], 2) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ number_format($profitSummary['request_count']) }} fees</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 uppercase">Fund card profit</p>
            <p class="text-xl font-bold text-blue-700">₦{{ number_format($profitSummary['topup_profit_ngn'], 2) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ number_format($profitSummary['topup_count']) }} top-ups</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 uppercase">Withdraw profit</p>
            <p class="text-xl font-bold text-violet-700">₦{{ number_format($profitSummary['withdraw_profit_ngn'], 2) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ number_format($profitSummary['withdraw_count']) }} withdraws</p>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
        <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 uppercase">Pending</p>
            <p class="text-2xl font-bold text-yellow-700">{{ number_format($stats['pending']) }}</p>
        </div>
        <div class="bg-white rounded-lg border border-violet-200 p-4 shadow-sm bg-violet-50/40">
            <p class="text-xs text-gray-500 uppercase">Preparing</p>
            <p class="text-2xl font-bold text-violet-700">{{ number_format($stats['preparing'] ?? 0) }}</p>
            <p class="text-xs text-gray-500 mt-1">Awaiting webhook</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 uppercase">Submitted</p>
            <p class="text-2xl font-bold text-blue-700">{{ number_format($stats['submitted']) }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 uppercase">Active</p>
            <p class="text-2xl font-bold text-green-700">{{ number_format($stats['active']) }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 uppercase">Failed</p>
            <p class="text-2xl font-bold text-red-700">{{ number_format($stats['failed']) }}</p>
        </div>
        <div class="bg-white rounded-lg border border-indigo-200 p-4 shadow-sm bg-indigo-50/50">
            <p class="text-xs text-gray-500 uppercase">Fees collected</p>
            <p class="text-xl font-bold text-gray-900">₦{{ number_format($stats['total_fees_ngn'], 2) }}</p>
            <p class="text-xs text-gray-500 mt-1">Preparing + submitted + active</p>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Status</label>
                <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">All</option>
                    <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                    <option value="preparing" @selected(request('status') === 'preparing')>Preparing</option>
                    <option value="submitted" @selected(request('status') === 'submitted')>Submitted</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="failed" @selected(request('status') === 'failed')>Failed</option>
                </select>
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs text-gray-500 mb-1">Search</label>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Phone, reference, card name, provider ID"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">From</label>
                <input type="date" name="from" value="{{ request('from') }}" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">To</label>
                <input type="date" name="to" value="{{ request('to') }}" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <button type="submit" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 text-sm">Filter</button>
            <a href="{{ route('admin.virtual-cards.index') }}" class="text-gray-600 hover:text-gray-900 text-sm py-2">Clear</a>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Wallet</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Card name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fee</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Provider ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($cards as $card)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm text-gray-900">#{{ $card->id }}</td>
                        <td class="px-6 py-4 text-sm">
                            <div class="font-medium text-gray-900">{{ $card->wallet?->displayName() ?? '—' }}</div>
                            <div class="text-xs text-gray-500">{{ $card->wallet?->phone_e164 ?? '—' }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">{{ $card->card_name ?? '—' }}</td>
                        <td class="px-6 py-4">@include('admin.virtual-cards._status-badge', ['status' => $card->status])</td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            ${{ number_format($card->fee_usd, 2) }}
                            <span class="text-gray-500 text-xs block">₦{{ number_format($card->fee_ngn, 2) }}</span>
                        </td>
                        <td class="px-6 py-4 text-xs font-mono text-gray-600">{{ $card->external_reference ?? '—' }}</td>
                        <td class="px-6 py-4 text-xs font-mono text-gray-600">{{ $card->card_external_id ?? '—' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $card->created_at->format('M d, Y H:i') }}</td>
                        <td class="px-6 py-4">
                            <a href="{{ route('admin.virtual-cards.show', $card) }}" class="text-primary hover:underline text-sm">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-6 py-8 text-center text-sm text-gray-500">No card requests found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($cards->hasPages())
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $cards->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
