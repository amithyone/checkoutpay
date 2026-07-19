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
            return Bank::query()
                ->orderBy('name')
                ->get(['code', 'name', 'logo_path'])
                ->map(fn (Bank $bank) => $bank->toApiArray())
                ->values()
                ->all();
        });
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
