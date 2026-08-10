<?php

namespace App\Services\Admin;

use App\Models\Business;
use App\Models\Payment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Import historical payments from a normalized CSV (e.g. legacy checzspw dump).
 *
 * Does not fire approval webhooks. Balance credit is opt-in and off by default.
 */
final class PaymentImportService
{
    public const DISK_DIR = 'payment-imports';

    public const SOURCE = 'legacy_import';

    /**
     * @return list<array{name: string, path: string, size: int, modified: int}>
     */
    public function listPreparedFiles(): array
    {
        $disk = Storage::disk('local');
        if (! $disk->exists(self::DISK_DIR)) {
            return [];
        }

        $files = [];
        foreach ($disk->files(self::DISK_DIR) as $path) {
            $base = basename($path);
            if (! preg_match('/\.(csv|csv\.gz)$/i', $base)) {
                continue;
            }
            $files[] = [
                'name' => $base,
                'path' => $path,
                'size' => (int) $disk->size($path),
                'modified' => (int) $disk->lastModified($path),
            ];
        }

        usort($files, fn ($a, $b) => $b['modified'] <=> $a['modified']);

        return $files;
    }

    /**
     * @param  array{
     *   business_id: int,
     *   dry_run?: bool,
     *   update_existing?: bool,
     *   credit_balances?: bool,
     *   only_status?: string|null,
     *   limit?: int|null
     * }  $options
     * @return array{ok: bool, message: string, created: int, updated: int, skipped: int, errors: list<string>, dry_run: bool}
     */
    public function importFromUpload(UploadedFile $file, array $options): array
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: '');
        $name = 'upload-'.now()->format('Ymd-His').'-'.Str::random(6).(($ext === 'gz' || str_ends_with(strtolower($file->getClientOriginalName()), '.csv.gz')) ? '.csv.gz' : '.csv');
        $stored = $file->storeAs(self::DISK_DIR.'/uploads', $name, 'local');

        return $this->importFromStoragePath((string) $stored, $options);
    }

    /**
     * @param  array{
     *   business_id: int,
     *   dry_run?: bool,
     *   update_existing?: bool,
     *   credit_balances?: bool,
     *   only_status?: string|null,
     *   limit?: int|null
     * }  $options
     * @return array{ok: bool, message: string, created: int, updated: int, skipped: int, errors: list<string>, dry_run: bool}
     */
    public function importFromStoragePath(string $storagePath, array $options): array
    {
        $absolute = Storage::disk('local')->path($storagePath);
        if (! is_file($absolute)) {
            return $this->result(false, 'File not found on server.', dryRun: (bool) ($options['dry_run'] ?? false));
        }

        return $this->importFromAbsolutePath($absolute, $options);
    }

    /**
     * @param  array{
     *   business_id: int,
     *   dry_run?: bool,
     *   update_existing?: bool,
     *   credit_balances?: bool,
     *   only_status?: string|null,
     *   limit?: int|null
     * }  $options
     * @return array{ok: bool, message: string, created: int, updated: int, skipped: int, errors: list<string>, dry_run: bool}
     */
    public function importFromAbsolutePath(string $absolutePath, array $options): array
    {
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $updateExisting = (bool) ($options['update_existing'] ?? false);
        $creditBalances = (bool) ($options['credit_balances'] ?? false);
        $onlyStatus = isset($options['only_status']) && $options['only_status'] !== ''
            ? (string) $options['only_status']
            : null;
        $limit = isset($options['limit']) && (int) $options['limit'] > 0
            ? (int) $options['limit']
            : null;

        $businessId = (int) ($options['business_id'] ?? 0);
        $business = Business::query()->find($businessId);
        if (! $business) {
            return $this->result(false, 'Select a valid business to attach imported payments to.', dryRun: $dryRun);
        }

        $handle = $this->openCsvHandle($absolutePath);
        if ($handle === null) {
            return $this->result(false, 'Could not open CSV (unsupported format).', dryRun: $dryRun);
        }

        $header = fgetcsv($handle);
        if (! is_array($header) || $header === []) {
            fclose($handle);

            return $this->result(false, 'CSV header row is missing.', dryRun: $dryRun);
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $required = ['transaction_id', 'amount', 'status'];
        foreach ($required as $col) {
            if (! in_array($col, $header, true)) {
                fclose($handle);

                return $this->result(false, "CSV missing required column: {$col}", dryRun: $dryRun);
            }
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $processed = 0;
        $balanceCreditTotal = 0.0;

        @set_time_limit(0);

        try {
            while (($row = fgetcsv($handle)) !== false) {
                if ($this->rowIsEmpty($row)) {
                    continue;
                }

                $assoc = [];
                foreach ($header as $i => $key) {
                    $assoc[$key] = $row[$i] ?? null;
                }

                $mapped = $this->mapRow($assoc, $businessId);
                if ($mapped === null) {
                    $skipped++;
                    continue;
                }

                if ($onlyStatus !== null && $mapped['status'] !== $onlyStatus) {
                    $skipped++;
                    continue;
                }

                if ($limit !== null && $processed >= $limit) {
                    break;
                }
                $processed++;

                try {
                    $outcome = $dryRun
                        ? $this->dryRunRow($mapped, $updateExisting)
                        : $this->persistRow($mapped, $updateExisting, $creditBalances, $business, $balanceCreditTotal);

                    if ($outcome === 'created') {
                        $created++;
                    } elseif ($outcome === 'updated') {
                        $updated++;
                    } else {
                        $skipped++;
                    }
                } catch (\Throwable $e) {
                    $errors[] = ($mapped['transaction_id'] ?? '?').': '.$e->getMessage();
                    if (count($errors) >= 25) {
                        $errors[] = '… further errors truncated';
                        break;
                    }
                }
            }
        } finally {
            fclose($handle);
        }

        $msg = $dryRun
            ? "Dry run: would create {$created}, update {$updated}, skip {$skipped}."
            : "Import finished: created {$created}, updated {$updated}, skipped {$skipped}.";

        if ($creditBalances && ! $dryRun && $balanceCreditTotal > 0) {
            $msg .= ' Credited ₦'.number_format($balanceCreditTotal, 2).' to business balance.';
        }

        Log::info('admin.payment_import', [
            'business_id' => $businessId,
            'file' => basename($absolutePath),
            'dry_run' => $dryRun,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => count($errors),
        ]);

        return $this->result(true, $msg, $created, $updated, $skipped, $errors, $dryRun);
    }

    /**
     * @param  array<string, mixed>  $assoc
     * @return array<string, mixed>|null
     */
    public function mapRow(array $assoc, int $businessId): ?array
    {
        $txn = trim((string) ($assoc['transaction_id'] ?? ''));
        if ($txn === '') {
            return null;
        }

        $amount = round((float) ($assoc['amount'] ?? 0), 2);
        if ($amount <= 0) {
            return null;
        }

        $status = strtolower(trim((string) ($assoc['status'] ?? 'pending')));
        $status = match ($status) {
            'success', 'successful', 'paid', 'completed', 'approved' => Payment::STATUS_APPROVED,
            'failed', 'fail', 'rejected', 'cancelled', 'canceled' => Payment::STATUS_REJECTED,
            default => Payment::STATUS_PENDING,
        };

        $charge = isset($assoc['charge']) && $assoc['charge'] !== '' ? round((float) $assoc['charge'], 2) : null;
        $received = isset($assoc['received_amount']) && $assoc['received_amount'] !== ''
            ? round((float) $assoc['received_amount'], 2)
            : null;

        $metaJson = (string) ($assoc['metadata_json'] ?? '');
        $meta = [];
        if ($metaJson !== '') {
            $decoded = json_decode($metaJson, true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }

        $emailData = array_merge($meta, [
            '_import' => [
                'source_system' => (string) ($assoc['source_system'] ?? 'csv_upload'),
                'legacy_id' => $assoc['legacy_id'] ?? null,
                'site_id' => $assoc['site_id'] ?? null,
                'site_name' => $assoc['site_name'] ?? null,
                'payer_email' => $assoc['payer_email'] ?? null,
                'description' => $assoc['description'] ?? null,
                'currency' => $assoc['currency'] ?? 'NGN',
                'imported_at' => now()->toIso8601String(),
            ],
        ]);

        $method = strtolower(trim((string) ($assoc['payment_method'] ?? '')));
        $methodUsed = match (true) {
            $method === '' => null,
            str_contains($method, 'card') => Payment::METHOD_CARD,
            default => Payment::METHOD_BANK_TRANSFER,
        };

        $createdAt = $this->parseTime($assoc['created_at'] ?? null) ?? now();
        $updatedAt = $this->parseTime($assoc['updated_at'] ?? null) ?? $createdAt;

        $businessReceives = $amount;
        $totalCharges = $charge;

        return [
            'transaction_id' => $txn,
            'amount' => $amount,
            'status' => $status,
            'business_id' => $businessId,
            'webhook_url' => '',
            'payer_name' => $this->nullableString($assoc['payer_name'] ?? null),
            'external_reference' => $this->nullableString($assoc['external_reference'] ?? null),
            'payment_source' => self::SOURCE,
            'payment_method_used' => $methodUsed,
            'total_charges' => $totalCharges,
            'business_receives' => $businessReceives,
            'received_amount' => $received,
            'email_data' => $emailData,
            'expires_at' => $status === Payment::STATUS_PENDING ? $createdAt : null,
            'matched_at' => $status === Payment::STATUS_APPROVED ? $updatedAt : null,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
            '_bank_label' => $this->nullableString($assoc['payment_method'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function dryRunRow(array $mapped, bool $updateExisting): string
    {
        $exists = Payment::withTrashed()->where('transaction_id', $mapped['transaction_id'])->exists();
        if ($exists) {
            return $updateExisting ? 'updated' : 'skipped';
        }

        return 'created';
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function persistRow(
        array $mapped,
        bool $updateExisting,
        bool $creditBalances,
        Business $business,
        float &$balanceCreditTotal,
    ): string {
        $bankLabel = $mapped['_bank_label'] ?? null;
        unset($mapped['_bank_label']);

        return DB::transaction(function () use ($mapped, $updateExisting, $creditBalances, $business, &$balanceCreditTotal, $bankLabel) {
            /** @var Payment|null $existing */
            $existing = Payment::withTrashed()->where('transaction_id', $mapped['transaction_id'])->first();

            if ($existing) {
                if (! $updateExisting) {
                    return 'skipped';
                }
                if ($existing->trashed()) {
                    $existing->restore();
                }
                $existing->fill(collect($mapped)->except(['transaction_id', 'created_at'])->all());
                if ($bankLabel) {
                    $existing->bank = $bankLabel;
                }
                $existing->updated_at = $mapped['updated_at'];
                $existing->save();

                return 'updated';
            }

            $payment = new Payment;
            $payment->fill(collect($mapped)->except(['created_at', 'updated_at'])->all());
            if ($bankLabel) {
                $payment->bank = (string) $bankLabel;
            }
            $payment->created_at = $mapped['created_at'];
            $payment->updated_at = $mapped['updated_at'];
            $payment->save();

            if (
                $creditBalances
                && $mapped['status'] === Payment::STATUS_APPROVED
                && (float) $mapped['business_receives'] > 0
            ) {
                $credit = (float) $mapped['business_receives'];
                $business->increment('balance', $credit);
                $balanceCreditTotal += $credit;
            }

            return 'created';
        });
    }

    /**
     * @return resource|null
     */
    private function openCsvHandle(string $absolutePath)
    {
        $lower = strtolower($absolutePath);
        if (str_ends_with($lower, '.csv.gz') || str_ends_with($lower, '.gz')) {
            $handle = @gzopen($absolutePath, 'rb');

            return $handle === false ? null : $handle;
        }

        $handle = @fopen($absolutePath, 'rb');

        return $handle === false ? null : $handle;
    }

    /**
     * @param  list<string|null>|false  $row
     */
    private function rowIsEmpty(array|false $row): bool
    {
        if ($row === false || $row === []) {
            return true;
        }

        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function parseTime(mixed $value): ?\Carbon\Carbon
    {
        $s = trim((string) ($value ?? ''));
        if ($s === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($s);
        } catch (\Throwable) {
            return null;
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $s = trim((string) ($value ?? ''));

        return $s === '' ? null : $s;
    }

    /**
     * @param  list<string>  $errors
     * @return array{ok: bool, message: string, created: int, updated: int, skipped: int, errors: list<string>, dry_run: bool}
     */
    private function result(
        bool $ok,
        string $message,
        int $created = 0,
        int $updated = 0,
        int $skipped = 0,
        array $errors = [],
        bool $dryRun = false,
    ): array {
        return [
            'ok' => $ok,
            'message' => $message,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
            'dry_run' => $dryRun,
        ];
    }
}
