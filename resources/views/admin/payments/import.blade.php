@extends('layouts.admin')

@section('title', 'Import Payments')
@section('page-title', 'Import Payments')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Import payments</h3>
            <p class="text-sm text-gray-600 mt-1">
                Upload a normalized CSV (or use a prepared legacy file) to add rows to Admin → Payments.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.payments.index') }}" class="bg-gray-100 text-gray-800 px-3 py-2 rounded-lg text-sm hover:bg-gray-200">
                ← Back to payments
            </a>
            <a href="{{ route('admin.payments.import.sample') }}" class="bg-white border border-gray-300 text-gray-800 px-3 py-2 rounded-lg text-sm hover:bg-gray-50">
                Download sample CSV
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 text-sm">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 text-sm">
            <ul class="list-disc ml-4">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(session('import_stats'))
        @php $stats = session('import_stats'); @endphp
        <div class="bg-white border border-gray-200 rounded-lg p-4 text-sm grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div><span class="text-gray-500">Created</span><div class="font-semibold text-lg">{{ $stats['created'] ?? 0 }}</div></div>
            <div><span class="text-gray-500">Updated</span><div class="font-semibold text-lg">{{ $stats['updated'] ?? 0 }}</div></div>
            <div><span class="text-gray-500">Skipped</span><div class="font-semibold text-lg">{{ $stats['skipped'] ?? 0 }}</div></div>
            <div><span class="text-gray-500">Mode</span><div class="font-semibold text-lg">{{ !empty($stats['dry_run']) ? 'Dry run' : 'Live' }}</div></div>
        </div>
    @endif

    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-sm text-amber-900">
        <p class="font-medium mb-1">Before you import</p>
        <ul class="list-disc ml-5 space-y-1">
            <li>Legacy status mapping: <code>success</code> → approved, <code>pending</code> → pending, <code>failed</code> → rejected.</li>
            <li>Rows attach to the business you select. This Contabo DB currently needs at least one business.</li>
            <li><strong>Credit balances</strong> is off by default — historical approved rows will appear in Payments without changing wallet balance.</li>
            <li>Large files (&gt;2MB): use a <strong>prepared server file</strong> below (or upload <code>.csv.gz</code>). Full approved set is ~3.6MB gzipped / ~33MB CSV.</li>
            <li>Start with a dry run and/or the sample file (<code>checzspw_transactions_sample_100.csv</code>).</li>
        </ul>
    </div>

    @if($businesses->isEmpty())
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-800">
            No businesses found. Create or restore a business first, then return here to import.
        </div>
    @endif

    <form method="POST" action="{{ route('admin.payments.import.store') }}" enctype="multipart/form-data" class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6 space-y-5">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Business</label>
            <select name="business_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" @disabled($businesses->isEmpty())>
                <option value="">Select business…</option>
                @foreach($businesses as $business)
                    <option value="{{ $business->id }}" @selected(old('business_id') == $business->id)>
                        {{ $business->name }} (#{{ $business->id }})
                    </option>
                @endforeach
            </select>
        </div>

        @php
            $defaultSource = old('source', count($preparedFiles) > 0 ? 'prepared' : 'upload');
        @endphp
        {{-- Always submit a source (disabled radios are omitted by browsers). --}}
        <input type="hidden" name="source" value="{{ $defaultSource }}" id="payment-import-source">

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="border border-gray-200 rounded-lg p-4">
                <label class="flex items-start gap-2 {{ count($preparedFiles) > 0 ? 'cursor-pointer' : 'opacity-60' }}">
                    <input type="radio" name="source_ui" value="prepared" class="mt-1 payment-import-source-radio"
                        @checked($defaultSource === 'prepared')
                        {{ count($preparedFiles) === 0 ? 'disabled' : '' }}
                        data-source="prepared">
                    <span>
                        <span class="font-medium text-gray-900">Prepared server file</span>
                        <span class="block text-xs text-gray-500 mt-0.5">From <code>storage/app/payment-imports/</code></span>
                        @if(count($preparedFiles) === 0)
                            <span class="block text-xs text-amber-700 mt-1">No prepared files on this server — use Upload, or copy CSVs into storage/app/payment-imports/.</span>
                        @endif
                    </span>
                </label>
                <select name="prepared_file" class="mt-3 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" @disabled(count($preparedFiles) === 0)>
                    <option value="">Choose file…</option>
                    @foreach($preparedFiles as $file)
                        <option value="{{ $file['name'] }}" @selected(old('prepared_file') === $file['name'])>
                            {{ $file['name'] }} ({{ number_format($file['size'] / 1048576, 2) }} MB)
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="border border-gray-200 rounded-lg p-4">
                <label class="flex items-start gap-2 cursor-pointer">
                    <input type="radio" name="source_ui" value="upload" class="mt-1 payment-import-source-radio"
                        @checked($defaultSource === 'upload')
                        data-source="upload">
                    <span>
                        <span class="font-medium text-gray-900">Upload CSV / CSV.GZ</span>
                        <span class="block text-xs text-gray-500 mt-0.5">Max ~50MB if PHP allows; prefer gzip for large sets</span>
                    </span>
                </label>
                <input type="file" name="csv_file" accept=".csv,.gz,.csv.gz,text/csv" class="mt-3 block w-full text-sm text-gray-700">
            </div>
        </div>
        <script>
            document.querySelectorAll('.payment-import-source-radio').forEach(function (el) {
                el.addEventListener('change', function () {
                    if (el.checked) {
                        document.getElementById('payment-import-source').value = el.getAttribute('data-source');
                    }
                });
            });
        </script>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Only status</label>
                <select name="only_status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">All rows in file</option>
                    <option value="approved" @selected(old('only_status') === 'approved')>Approved only</option>
                    <option value="pending" @selected(old('only_status') === 'pending')>Pending only</option>
                    <option value="rejected" @selected(old('only_status') === 'rejected')>Rejected only</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Limit rows</label>
                <input type="number" name="limit" value="{{ old('limit') }}" min="1" max="200000" placeholder="No limit"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-800 mt-6 sm:mt-8">
                <input type="checkbox" name="dry_run" value="1" class="rounded" @checked(old('dry_run', true))>
                Dry run (no writes)
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-800 mt-6 sm:mt-8">
                <input type="checkbox" name="update_existing" value="1" class="rounded" @checked(old('update_existing'))>
                Update if transaction_id exists
            </label>
        </div>

        <label class="flex items-start gap-2 text-sm text-red-800 bg-red-50 border border-red-100 rounded-lg p-3">
            <input type="checkbox" name="credit_balances" value="1" class="rounded mt-0.5" @checked(old('credit_balances'))>
            <span>
                <span class="font-medium">Also credit business balance</span> for newly created <em>approved</em> rows
                (amount credited = payment amount). Leave unchecked for history-only import.
            </span>
        </label>

        <div class="flex flex-wrap gap-2">
            <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg text-sm hover:opacity-90 disabled:opacity-50"
                @disabled($businesses->isEmpty())>
                Run import
            </button>
        </div>
    </form>

    <div class="bg-white rounded-lg border border-gray-200 p-4 text-sm text-gray-600">
        <p class="font-medium text-gray-900 mb-2">Expected CSV columns</p>
        <p class="font-mono text-xs break-all">{{ implode(', ', $sampleHeaders) }}</p>
    </div>
</div>
@endsection
