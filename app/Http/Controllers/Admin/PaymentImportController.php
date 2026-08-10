<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\Admin\PaymentImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentImportController extends Controller
{
    public function __construct(
        private PaymentImportService $imports,
    ) {}

    public function create(): View
    {
        return view('admin.payments.import', [
            'businesses' => Business::query()->orderBy('name')->get(['id', 'name', 'email']),
            'preparedFiles' => $this->imports->listPreparedFiles(),
            'sampleHeaders' => [
                'legacy_id', 'transaction_id', 'external_reference', 'amount', 'status', 'payment_method',
                'payer_name', 'payer_email', 'site_id', 'site_name', 'description', 'charge', 'received_amount',
                'currency', 'created_at', 'updated_at', 'metadata_json', 'source_system',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'business_id' => 'required|integer|exists:businesses,id',
            'source' => 'required|in:upload,prepared',
            'csv_file' => 'nullable|file|max:51200',
            'prepared_file' => 'nullable|string|max:255',
            'dry_run' => 'nullable|boolean',
            'update_existing' => 'nullable|boolean',
            'credit_balances' => 'nullable|boolean',
            'only_status' => 'nullable|in:pending,approved,rejected',
            'limit' => 'nullable|integer|min:1|max:200000',
        ]);

        $options = [
            'business_id' => (int) $validated['business_id'],
            'dry_run' => $request->boolean('dry_run'),
            'update_existing' => $request->boolean('update_existing'),
            'credit_balances' => $request->boolean('credit_balances'),
            'only_status' => $validated['only_status'] ?? null,
            'limit' => isset($validated['limit']) ? (int) $validated['limit'] : null,
        ];

        if ($validated['source'] === 'upload') {
            if (! $request->hasFile('csv_file')) {
                return back()->withInput()->with('error', 'Choose a CSV or .csv.gz file to upload.');
            }
            $file = $request->file('csv_file');
            $name = strtolower($file->getClientOriginalName());
            if (! str_ends_with($name, '.csv') && ! str_ends_with($name, '.csv.gz') && ! str_ends_with($name, '.gz')) {
                return back()->withInput()->with('error', 'Only .csv or .csv.gz files are accepted.');
            }
            $result = $this->imports->importFromUpload($file, $options);
        } else {
            $prepared = basename((string) ($validated['prepared_file'] ?? ''));
            if ($prepared === '' || preg_match('/[\\\\\\/]/', $prepared)) {
                return back()->withInput()->with('error', 'Choose a prepared file from the list.');
            }
            $path = PaymentImportService::DISK_DIR.'/'.$prepared;
            $result = $this->imports->importFromStoragePath($path, $options);
        }

        $flash = $result['ok'] ? 'success' : 'error';
        $message = $result['message'];
        if ($result['errors'] !== []) {
            $message .= ' Errors: '.implode(' | ', array_slice($result['errors'], 0, 5));
        }

        return back()->with($flash, $message)->with('import_stats', $result);
    }

    public function downloadSample(): StreamedResponse
    {
        $headers = [
            'legacy_id', 'transaction_id', 'external_reference', 'amount', 'status', 'payment_method',
            'payer_name', 'payer_email', 'site_id', 'site_name', 'description', 'charge', 'received_amount',
            'currency', 'created_at', 'updated_at', 'metadata_json', 'source_system',
        ];

        return response()->streamDownload(function () use ($headers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            fputcsv($out, [
                '1', 'SAMPLE-REF-001', 'ext-1', '1000.00', 'approved', 'xtrapay',
                'Sample Payer', 'sample@example.com', '7', 'demo-site', 'Deposit via Xtrapay', '50', '1050',
                'NGN', '2025-07-11 12:00:00', '2025-07-11 12:01:00', '{}', 'checzspw_payment',
            ]);
            fclose($out);
        }, 'payment-import-sample.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
