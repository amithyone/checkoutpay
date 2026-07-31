<?php

namespace App\Services;

use App\Models\Bank;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class BankLogoService
{
    public function cacheKey(): string
    {
        return (string) config('bank_logos.cache_key', 'banks:list:with-logos:v1');
    }

    public function forgetListCache(): void
    {
        Cache::forget($this->cacheKey());
        Cache::forget('rentals:banks:list:v1');
        Cache::forget('rentals:banks:list:v2');
    }

    /**
     * @return list<array{code: string, name: string, logo_url: string|null}>
     */
    public function listForApi(): array
    {
        return Cache::remember($this->cacheKey(), now()->addHours(6), function () {
            $rows = Bank::query()
                ->orderBy('name')
                ->get(['code', 'name', 'logo_path'])
                ->map(fn (Bank $bank) => $bank->toApiArray())
                ->values()
                ->all();

            return $this->dedupeApiBankRows($rows);
        });
    }

    /**
     * Collapse legacy short codes and duplicate institution names into one row per bank.
     *
     * @param  list<array{code: string, name: string, logo_url: string|null}>  $rows
     * @return list<array{code: string, name: string, logo_url: string|null}>
     */
    private function dedupeApiBankRows(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $byNip = [];
        foreach ($rows as $row) {
            $nip = NigerianBankCodeNormalizer::toNipTransferCode((string) ($row['code'] ?? ''));
            if ($nip === '') {
                continue;
            }
            $this->mergeApiBankCandidate($byNip, $nip, $row, $nip);
        }

        $deduped = array_values(array_map(fn (array $row) => $this->finalizeApiBankRow($row), $byNip));

        $byInstitution = [];
        foreach ($deduped as $row) {
            $key = $this->institutionKey((string) ($row['name'] ?? ''));
            if ($key === '') {
                $key = 'code:'.($row['code'] ?? '');
            }
            $nip = NigerianBankCodeNormalizer::toNipTransferCode((string) ($row['code'] ?? ''));
            $this->mergeApiBankCandidate($byInstitution, $key, $row, $nip);
        }

        $final = array_values(array_map(fn (array $row) => $this->finalizeApiBankRow($row), $byInstitution));
        usort($final, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

        return $final;
    }

    /**
     * @param  array<string, array<string, mixed>>  $bucket
     * @param  array{code: string, name: string, logo_url: string|null}  $row
     */
    private function mergeApiBankCandidate(array &$bucket, string $key, array $row, string $nip): void
    {
        $candidate = [
            'code' => (string) ($row['code'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'logo_url' => $row['logo_url'] ?? null,
            '_nip' => $nip,
            '_score' => $this->apiBankRowScore($row, $nip),
        ];

        if (! isset($bucket[$key])) {
            $bucket[$key] = $candidate;

            return;
        }

        if ($candidate['_score'] > $bucket[$key]['_score']) {
            $bucket[$key] = $candidate;

            return;
        }

        if ($candidate['_score'] === $bucket[$key]['_score']) {
            $existingNip = (string) ($bucket[$key]['_nip'] ?? '');
            if ($this->isCanonicalNipCode($nip) && ! $this->isCanonicalNipCode($existingNip)) {
                $bucket[$key] = $candidate;

                return;
            }
            if ($this->displayNameScore($candidate['name']) > $this->displayNameScore($bucket[$key]['name'])) {
                $bucket[$key]['name'] = $candidate['name'];
            }
            if ($candidate['logo_url'] && ! $bucket[$key]['logo_url']) {
                $bucket[$key]['logo_url'] = $candidate['logo_url'];
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{code: string, name: string, logo_url: string|null}
     */
    private function finalizeApiBankRow(array $row): array
    {
        $nip = (string) ($row['_nip'] ?? '');
        $code = (string) ($row['code'] ?? '');
        $apiCode = strlen($nip) >= 6 ? $nip : $code;

        return [
            'code' => $apiCode,
            'name' => $this->formatBankDisplayName((string) ($row['name'] ?? '')),
            'logo_url' => $row['logo_url'] ?? null,
        ];
    }

    /**
     * @param  array{code: string, name: string, logo_url: string|null}  $row
     */
    private function apiBankRowScore(array $row, string $nip): int
    {
        $code = (string) ($row['code'] ?? '');
        $digits = preg_replace('/\D/', '', $code) ?? '';
        $score = 0;

        if (! empty($row['logo_url'])) {
            $score += 100;
        }
        if ($nip !== '' && $code === $nip) {
            $score += 50;
        }
        if (strlen($digits) >= 6) {
            $score += 25;
        }
        if ($this->isCanonicalNipCode($nip)) {
            $score += 40;
        }
        $score += $this->displayNameScore((string) ($row['name'] ?? ''));

        return $score;
    }

    private function isCanonicalNipCode(string $nip): bool
    {
        if ($nip === '') {
            return false;
        }

        $legacyMap = config('nigerian_bank_legacy_to_nip', []);
        if (is_array($legacyMap) && in_array($nip, array_values($legacyMap), true)) {
            return true;
        }

        $byCode = config('bank_logos.by_code', []);
        if (is_array($byCode) && array_key_exists($nip, $byCode)) {
            return true;
        }

        return false;
    }

    private function displayNameScore(string $name): int
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return 0;
        }

        return $trimmed !== strtoupper($trimmed) ? 10 : 5;
    }

    private function institutionKey(string $name): string
    {
        $n = $this->normalizeName($name);
        if ($n === '') {
            return '';
        }

        $stop = ['microfinance bank', 'microfinance', 'mfb', 'plc', 'ltd', 'limited', 'nigeria', 'ng', 'bank'];
        foreach ($stop as $word) {
            $n = preg_replace('/\b'.preg_quote($word, '/').'\b/', ' ', $n) ?? $n;
        }
        $n = preg_replace('/\s+/', ' ', $n) ?? $n;

        return trim($n);
    }

    private function formatBankDisplayName(string $name): string
    {
        $trimmed = trim($name);
        if ($trimmed === '' || $trimmed !== strtoupper($trimmed)) {
            return $trimmed;
        }

        return ucwords(strtolower($trimmed));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function enrichRowsWithLogoUrl(array $rows): array
    {
        $codes = [];
        foreach ($rows as $row) {
            $code = $row['bankCode'] ?? $row['bank_code'] ?? $row['code'] ?? null;
            if ($code === null || $code === '') {
                continue;
            }
            $nip = NigerianBankCodeNormalizer::toNipTransferCode((string) $code);
            if ($nip !== '') {
                $codes[$nip] = true;
                $codes[(string) $code] = true;
            }
        }

        if ($codes === []) {
            return array_map(function (array $row) {
                $row['logo_url'] = $row['logo_url'] ?? null;

                return $row;
            }, $rows);
        }

        $logos = Bank::query()
            ->whereIn('code', array_keys($codes))
            ->whereNotNull('logo_path')
            ->where('logo_path', '!=', '')
            ->get(['code', 'logo_path'])
            ->keyBy('code');

        return array_map(function (array $row) use ($logos) {
            $code = (string) ($row['bankCode'] ?? $row['bank_code'] ?? $row['code'] ?? '');
            $nip = $code !== '' ? NigerianBankCodeNormalizer::toNipTransferCode($code) : '';
            $bank = $logos->get($nip) ?? $logos->get($code);
            $row['logo_url'] = $bank instanceof Bank ? $bank->logoUrl() : null;

            return $row;
        }, $rows);
    }

    /**
     * @return list<string>
     */
    public function libraryFilenames(): array
    {
        $dir = (string) config('bank_logos.library_path');
        if ($dir === '' || ! is_dir($dir)) {
            return [];
        }

        $files = File::files($dir);
        $names = [];
        foreach ($files as $file) {
            if (strtolower($file->getExtension()) === 'svg') {
                $names[] = $file->getFilename();
            }
        }
        sort($names);

        return $names;
    }

    public function resolveLibraryFile(?string $filename): ?string
    {
        if ($filename === null || $filename === '') {
            return null;
        }
        $filename = basename($filename);
        if (! str_ends_with(strtolower($filename), '.svg')) {
            return null;
        }
        $path = rtrim((string) config('bank_logos.library_path'), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$filename;
        if (! is_file($path)) {
            return null;
        }

        return $path;
    }

    public function suggestLibraryFilename(Bank $bank): ?string
    {
        $byCode = config('bank_logos.by_code', []);
        if (is_array($byCode)) {
            $nip = NigerianBankCodeNormalizer::toNipTransferCode((string) $bank->code);
            if ($nip !== '' && isset($byCode[$nip]) && is_string($byCode[$nip]) && $this->resolveLibraryFile($byCode[$nip])) {
                return $byCode[$nip];
            }
        }

        $normalized = $this->normalizeName((string) $bank->name);
        $byName = config('bank_logos.by_name_contains', []);
        if (! is_array($byName)) {
            return null;
        }

        foreach ($byName as $needle => $file) {
            if (! is_string($needle) || ! is_string($file)) {
                continue;
            }
            $n = $this->normalizeName($needle);
            if ($n !== '' && str_contains($normalized, $n) && $this->resolveLibraryFile($file)) {
                // Avoid matching "uba" inside unrelated names poorly — require word-ish for short needles
                if (strlen($n) <= 3 && ! preg_match('/\b'.preg_quote($n, '/').'\b/', $normalized)) {
                    continue;
                }

                return $file;
            }
        }

        return null;
    }

    /**
     * @return array{mapped: int, skipped: int, missing_library: int}
     */
    public function autoMap(bool $force = false): array
    {
        $mapped = 0;
        $skipped = 0;
        $missing = 0;

        Bank::query()->orderBy('id')->chunkById(200, function ($banks) use ($force, &$mapped, &$skipped, &$missing) {
            foreach ($banks as $bank) {
                if (! $force && $bank->hasLogo()) {
                    $skipped++;

                    continue;
                }

                $file = $this->suggestLibraryFilename($bank);
                if ($file === null) {
                    $skipped++;

                    continue;
                }
                if (! $this->resolveLibraryFile($file)) {
                    $missing++;

                    continue;
                }

                $this->assignLibraryLogo($bank, $file, 'nbl');
                $mapped++;
            }
        });

        $this->forgetListCache();

        return [
            'mapped' => $mapped,
            'skipped' => $skipped,
            'missing_library' => $missing,
        ];
    }

    public function assignLibraryLogo(Bank $bank, string $filename, string $source = 'nbl'): void
    {
        $abs = $this->resolveLibraryFile($filename);
        if ($abs === null) {
            throw new \InvalidArgumentException('Library logo not found: '.$filename);
        }

        $disk = (string) config('bank_logos.disk', 'public');
        $dir = trim((string) config('bank_logos.directory', 'bank-logos'), '/');
        $ext = pathinfo($filename, PATHINFO_EXTENSION) ?: 'svg';
        $dest = $dir.'/'.$bank->code.'.'.$ext;

        if ($bank->logo_path && Storage::disk($disk)->exists($bank->logo_path) && $bank->logo_path !== $dest) {
            Storage::disk($disk)->delete($bank->logo_path);
        }

        Storage::disk($disk)->put($dest, File::get($abs));

        $bank->logo_path = $dest;
        $bank->logo_source = $source;
        $bank->save();
        $this->forgetListCache();
    }

    public function storeUpload(Bank $bank, UploadedFile $file): void
    {
        $disk = (string) config('bank_logos.disk', 'public');
        $dir = trim((string) config('bank_logos.directory', 'bank-logos'), '/');
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'svg');
        if (! in_array($ext, ['svg', 'png', 'jpg', 'jpeg', 'webp'], true)) {
            $ext = 'svg';
        }
        $dest = $dir.'/'.$bank->code.'.'.$ext;

        if ($bank->logo_path && Storage::disk($disk)->exists($bank->logo_path)) {
            Storage::disk($disk)->delete($bank->logo_path);
        }

        Storage::disk($disk)->putFileAs($dir, $file, $bank->code.'.'.$ext);

        $bank->logo_path = $dest;
        $bank->logo_source = 'upload';
        $bank->save();
        $this->forgetListCache();
    }

    public function clearLogo(Bank $bank): void
    {
        $disk = (string) config('bank_logos.disk', 'public');
        if ($bank->logo_path && Storage::disk($disk)->exists($bank->logo_path)) {
            Storage::disk($disk)->delete($bank->logo_path);
        }
        $bank->logo_path = null;
        $bank->logo_source = null;
        $bank->save();
        $this->forgetListCache();
    }

    private function normalizeName(string $name): string
    {
        $n = strtolower(trim($name));
        $n = preg_replace('/[^a-z0-9\s]/', ' ', $n) ?? $n;
        $n = preg_replace('/\s+/', ' ', $n) ?? $n;

        return trim($n);
    }
}
