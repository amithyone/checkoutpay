<?php

namespace App\Services\WalletImport;

use App\Services\Whatsapp\PhoneNormalizer;
use App\Services\WhatsappWalletBankPayoutService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Sterilize Form Responses CSV rows into seedable wallet records.
 */
final class FormCsvWalletSterilizerService
{
    public function __construct(
        private FormCsvBankResolver $banks,
        private WhatsappWalletBankPayoutService $bankPayout,
    ) {}

    /**
     * @param  (callable(int $done, int $totalApprox, array $record): void)|null  $onProgress
     * @return array{
     *   header: list<string>,
     *   rows: list<array<string, mixed>>,
     *   summary: array<string, int>
     * }
     */
    public function sterilizeFile(string $csvPath, bool $skipNameEnquiry = false, int $limit = 0, ?callable $onProgress = null): array
    {
        if (! is_readable($csvPath)) {
            throw new \InvalidArgumentException('CSV not readable: '.$csvPath);
        }

        $handle = fopen($csvPath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Could not open CSV: '.$csvPath);
        }

        try {
            $header = fgetcsv($handle);
            if (! is_array($header) || $header === []) {
                throw new \RuntimeException('CSV has no header row.');
            }
            if (isset($header[0])) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]) ?? (string) $header[0];
            }

            $indexes = $this->columnIndexes($header);
            $out = [];
            $summary = [
                'total' => 0,
                'ok' => 0,
                'needs_review' => 0,
                'reject' => 0,
                'name_enquiry_ok' => 0,
                'name_enquiry_fail' => 0,
                'tier_target_1' => 0,
                'tier_target_2' => 0,
            ];

            $rowNum = 1;
            while (($raw = fgetcsv($handle)) !== false) {
                $rowNum++;
                if (! is_array($raw) || $this->rowEmpty($raw)) {
                    continue;
                }
                if ($limit > 0 && $summary['total'] >= $limit) {
                    break;
                }
                $summary['total']++;
                $record = $this->sterilizeRow($raw, $indexes, $rowNum, $skipNameEnquiry);
                $out[] = $record;
                $status = (string) ($record['status'] ?? 'reject');
                $summary[$status] = ($summary[$status] ?? 0) + 1;
                if (($record['name_source'] ?? '') === 'name_enquiry') {
                    $summary['name_enquiry_ok']++;
                } elseif (! empty($record['account_number']) && ! empty($record['bank_code']) && ! $skipNameEnquiry) {
                    $summary['name_enquiry_fail']++;
                }
                $tt = (int) ($record['tier_target'] ?? 1);
                if ($tt === 2) {
                    $summary['tier_target_2']++;
                } else {
                    $summary['tier_target_1']++;
                }
                if ($onProgress !== null) {
                    $onProgress($summary['total'], $limit > 0 ? $limit : 849, $record);
                }
            }
        } finally {
            fclose($handle);
        }

        return [
            'header' => $header,
            'rows' => $out,
            'summary' => $summary,
        ];
    }

    /**
     * @param  list<string>  $header
     * @return array<string, int|list<int>>
     */
    public function columnIndexes(array $header): array
    {
        $map = [];
        $acct = [];
        foreach ($header as $i => $name) {
            $key = trim((string) $name);
            if ($key === 'Account Number') {
                $acct[] = $i;
                continue;
            }
            if (! array_key_exists($key, $map)) {
                $map[$key] = $i;
            }
        }
        $map['__account_numbers'] = $acct;

        return $map;
    }

    /**
     * @param  list<string|null>  $raw
     * @param  array<string, int|list<int>>  $indexes
     * @return array<string, mixed>
     */
    public function sterilizeRow(array $raw, array $indexes, int $rowNum, bool $skipNameEnquiry = false): array
    {
        $cell = function (string $name) use ($raw, $indexes): string {
            if (! isset($indexes[$name]) || ! is_int($indexes[$name])) {
                return '';
            }
            $i = $indexes[$name];

            return trim((string) ($raw[$i] ?? ''));
        };

        $acctIndexes = $indexes['__account_numbers'] ?? [];
        $acctRaw = '';
        if (is_array($acctIndexes)) {
            foreach (array_reverse($acctIndexes) as $i) {
                $v = trim((string) ($raw[$i] ?? ''));
                if ($v !== '') {
                    $acctRaw = $v;
                    break;
                }
            }
            if ($acctRaw === '' && $acctIndexes !== []) {
                $acctRaw = trim((string) ($raw[$acctIndexes[0]] ?? ''));
            }
        }

        $phoneRaw = $cell('Phone Number');
        $phone = PhoneNormalizer::canonicalNgE164Digits($phoneRaw);
        $bvnDigits = preg_replace('/\D+/', '', $cell('BVN')) ?? '';
        $bvn = strlen($bvnDigits) === 11 ? $bvnDigits : null;
        $dob = $this->parseDob($cell('Date Of Birth'));
        $gender = $this->normalizeGender($cell('Gender'));
        $email = strtolower(trim($cell('Email Address')));
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = '';
        }

        $bankRaw = $cell('Bank');
        $bankResolved = $this->banks->resolve($bankRaw);
        $bankCode = $bankResolved['bank_code'] ?? null;
        $account = preg_replace('/\D+/', '', $acctRaw) ?? '';
        $account = strlen($account) === 10 ? $account : (strlen($account) > 10 ? substr($account, -10) : $account);
        if (strlen($account) !== 10) {
            $account = '';
        }

        $csvFirst = trim($cell('Cleaned First Name') ?: $cell('Name'));
        $csvLast = trim($cell('Cleaned Surname') ?: $cell('Surname'));
        $csvFull = trim($csvFirst.' '.$csvLast);

        $errors = [];
        $needsReview = false;
        $nameSource = 'csv_fallback';
        $confirmedFull = '';
        $fname = $csvFirst;
        $lname = $csvLast;

        if ($phone === null) {
            $errors[] = 'invalid_phone';
        }

        if ($bankRaw !== '' && $bankCode === null) {
            $errors[] = 'unmatched_bank';
            $needsReview = true;
        }

        if ($account === '' && $bankCode !== null) {
            $errors[] = 'invalid_account';
            $needsReview = true;
        }

        if ($account !== '' && $bankCode !== null && ! $skipNameEnquiry) {
            $ne = $this->cachedNameEnquiry($bankCode, $account);
            if ($ne !== null && trim((string) ($ne['account_name'] ?? '')) !== '') {
                $confirmedFull = trim((string) $ne['account_name']);
                [$fname, $lname] = $this->splitPersonName($confirmedFull);
                $nameSource = 'name_enquiry';
            } else {
                $needsReview = true;
                $errors[] = 'name_enquiry_failed';
            }
        } elseif ($account === '' || $bankCode === null) {
            if ($csvFull === '') {
                $errors[] = 'no_name_and_no_bank_account';
            } else {
                $needsReview = true;
                $errors[] = 'missing_bank_or_account';
            }
        }

        if ($fname === '' && $lname === '' && $confirmedFull === '') {
            $errors[] = 'empty_name';
        }

        if ($confirmedFull === '') {
            $confirmedFull = trim($fname.' '.$lname);
        }

        $tierTarget = $bvn !== null ? 2 : 1;

        $fatal = array_intersect($errors, ['invalid_phone', 'no_name_and_no_bank_account', 'empty_name']);
        if ($fatal !== []) {
            $status = 'reject';
        } elseif ($needsReview || $errors !== []) {
            $status = 'needs_review';
        } else {
            $status = 'ok';
        }

        // Reject only when phone unusable; keep needs_review seedable with --only-ok false.
        if (in_array('invalid_phone', $errors, true)) {
            $status = 'reject';
        }

        return [
            'row_num' => $rowNum,
            'status' => $status,
            'needs_review' => $status === 'needs_review',
            'errors' => array_values($errors),
            'phone_raw' => $phoneRaw,
            'phone_e164' => $phone,
            'email' => $email !== '' ? $email : null,
            'gender' => $gender,
            'dob' => $dob,
            'bvn' => $bvn,
            'bvn_ok' => $bvn !== null,
            'tier_target' => $tierTarget,
            'bank_raw' => $bankRaw !== '' ? $bankRaw : null,
            'bank_code' => $bankCode,
            'bank_resolved_via' => $bankResolved['resolved_via'] ?? null,
            'account_number' => $account !== '' ? $account : null,
            'name_csv' => $csvFull !== '' ? $csvFull : null,
            'name_csv_first' => $csvFirst !== '' ? $csvFirst : null,
            'name_csv_last' => $csvLast !== '' ? $csvLast : null,
            'confirmed_account_name' => $confirmedFull !== '' ? $confirmedFull : null,
            'kyc_fname' => $fname !== '' ? $fname : null,
            'kyc_lname' => $lname !== '' ? $lname : null,
            'name_source' => $nameSource,
            'address' => $cell('Address( LGA & State)') ?: null,
            'project' => $cell('Project') ?: null,
            'timestamp' => $cell('Timestamp') ?: null,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function splitPersonName(string $full): array
    {
        $full = preg_replace('/\s+/u', ' ', trim($full)) ?? trim($full);
        if ($full === '') {
            return ['', ''];
        }
        $parts = preg_split('/\s+/u', $full) ?: [];
        if (count($parts) === 1) {
            return [$parts[0], ''];
        }
        $first = array_shift($parts);

        return [(string) $first, implode(' ', $parts)];
    }

    public function parseDob(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $formats = [
            'Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'm-d-Y',
            'd/m/y', 'm/d/y', 'Y/m/d', 'd.m.Y',
        ];
        foreach ($formats as $fmt) {
            try {
                $dt = Carbon::createFromFormat($fmt, $raw);
                if ($dt !== false) {
                    $y = (int) $dt->year;
                    if ($y >= 1920 && $y <= ((int) date('Y') - 10)) {
                        return $dt->format('Y-m-d');
                    }
                }
            } catch (\Throwable) {
                // try next
            }
        }

        try {
            $dt = Carbon::parse($raw);
            $y = (int) $dt->year;
            if ($y >= 1920 && $y <= ((int) date('Y') - 10)) {
                return $dt->format('Y-m-d');
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    public function normalizeGender(string $raw): ?string
    {
        $g = strtolower(trim($raw));
        if (in_array($g, ['m', 'male', 'man'], true)) {
            return 'male';
        }
        if (in_array($g, ['f', 'female', 'woman'], true)) {
            return 'female';
        }

        return null;
    }

    /**
     * @return array{account_name: string, bank_code: string}|null
     */
    private function cachedNameEnquiry(string $bankCode, string $account10): ?array
    {
        if (! $this->bankPayout->isNameEnquiryAvailable()) {
            return null;
        }

        $ttl = max(60, (int) config('wallet_import_banks.name_enquiry_cache_seconds', 604800));
        $key = 'wallet_import_ne:'.md5(strtolower(trim($bankCode)).'|'.$account10);

        /** @var array{account_name: string, bank_code: string}|null|false $cached */
        $cached = Cache::remember($key, $ttl, function () use ($bankCode, $account10) {
            $delay = max(0, (int) config('wallet_import_banks.name_enquiry_delay_ms', 150));
            if ($delay > 0) {
                usleep($delay * 1000);
            }
            // Primary code only (bulk-safe). Full multi-variant enquiry is too slow for ~850 rows.
            $ne = $this->bankPayout->nameEnquiryPrimary($bankCode, $account10);
            if (! is_array($ne) || trim((string) ($ne['account_name'] ?? '')) === '') {
                return false;
            }
            if ($this->bankPayout->isWeakVerifiedName($ne['account_name'] ?? null)) {
                return false;
            }

            return [
                'account_name' => trim((string) $ne['account_name']),
                'bank_code' => (string) ($ne['bank_code'] ?? $bankCode),
            ];
        });

        return is_array($cached) ? $cached : null;
    }

    /**
     * @param  list<string|null>  $raw
     */
    private function rowEmpty(array $raw): bool
    {
        foreach ($raw as $v) {
            if (trim((string) $v) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function writeJsonl(string $path, array $rows): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $fh = fopen($path, 'wb');
        if ($fh === false) {
            throw new \RuntimeException('Cannot write '.$path);
        }
        try {
            foreach ($rows as $row) {
                fwrite($fh, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
            }
        } finally {
            fclose($fh);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function writeReportCsv(string $path, array $rows): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $fh = fopen($path, 'wb');
        if ($fh === false) {
            throw new \RuntimeException('Cannot write '.$path);
        }
        try {
            fputcsv($fh, [
                'row_num', 'status', 'phone_e164', 'bank_raw', 'bank_code', 'account_number',
                'name_csv', 'confirmed_account_name', 'name_source', 'bvn_ok', 'tier_target',
                'dob', 'email', 'errors',
            ]);
            foreach ($rows as $r) {
                fputcsv($fh, [
                    $r['row_num'] ?? '',
                    $r['status'] ?? '',
                    $r['phone_e164'] ?? '',
                    $r['bank_raw'] ?? '',
                    $r['bank_code'] ?? '',
                    $r['account_number'] ?? '',
                    $r['name_csv'] ?? '',
                    $r['confirmed_account_name'] ?? '',
                    $r['name_source'] ?? '',
                    ! empty($r['bvn_ok']) ? '1' : '0',
                    $r['tier_target'] ?? '',
                    $r['dob'] ?? '',
                    $r['email'] ?? '',
                    implode('|', $r['errors'] ?? []),
                ]);
            }
        } finally {
            fclose($fh);
        }
    }
}
