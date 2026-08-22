<?php

namespace App\Services\LiveSync;

use App\Models\LiveSyncRowMap;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Config-driven serialize / probe / upsert for common money-path tables.
 */
final class LiveSyncGenericEngine
{
    /**
     * @return array<string, mixed>
     */
    public function entityConfig(string $entity): array
    {
        $cfg = config('live_sync.entities.'.$entity);
        if (! is_array($cfg) || empty($cfg['model'])) {
            throw new \InvalidArgumentException("Unknown live sync entity: {$entity}");
        }

        return $cfg;
    }

    /**
     * @return list<string>
     */
    public function commonEntities(): array
    {
        return array_values(array_filter(
            (array) config('live_sync.common_order', []),
            fn ($e) => is_string($e) && is_array(config('live_sync.entities.'.$e)),
        ));
    }

    /**
     * Customer-liability rows that drive /enter0/audits site float vs Mevon.
     *
     * @return list<string>
     */
    public function floatEntities(): array
    {
        return array_values(array_filter(
            (array) config('live_sync.float_order', ['renter', 'business', 'whatsapp_wallet']),
            fn ($e) => is_string($e) && is_array(config('live_sync.entities.'.$e)),
        ));
    }

    /**
     * @return list<string>
     */
    public function observableEntities(): array
    {
        $out = [];
        foreach ((array) config('live_sync.entities', []) as $name => $cfg) {
            if (! empty($cfg['observe'])) {
                $out[] = (string) $name;
            }
        }

        return $out;
    }

    public function modelClass(string $entity): string
    {
        return (string) $this->entityConfig($entity)['model'];
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(string $entity, Model $model): array
    {
        $cfg = $this->entityConfig($entity);
        $exclude = array_merge(['id'], (array) ($cfg['exclude'] ?? []));
        $attrs = Arr::except($model->getAttributes(), $exclude);

        // Use cast values for JSON/array columns (raw DB strings can be double-encoded).
        foreach ($model->getCasts() as $column => $cast) {
            if (! array_key_exists($column, $attrs)) {
                continue;
            }
            if (in_array($cast, ['array', 'json', 'object', 'collection'], true)
                || (is_string($cast) && str_starts_with($cast, 'AsArray'))) {
                $attrs[$column] = $model->getAttribute($column);
            }
        }
        if ($entity === 'payment' && array_key_exists('webhook_urls_sent', $attrs)) {
            $attrs['webhook_urls_sent'] = $model->webhook_urls_sent;
        }

        // Cast dates to ISO strings for JSON transport
        foreach ($attrs as $k => $v) {
            if ($v instanceof \DateTimeInterface) {
                $attrs[$k] = $v->format(DATE_ATOM);
            }
        }

        $data = $attrs;
        $data['_origin_id'] = (int) $model->getKey();

        foreach ((array) ($cfg['extras'] ?? []) as $extraKey => $path) {
            $data[$extraKey] = data_get($model, $path);
        }

        // Mevon ledger preferred natural keys
        if ($entity === 'mevon_pay_ledger_entry') {
            $ext = trim((string) ($model->external_reference ?? ''));
            $pay = trim((string) ($model->payout_reference ?? ''));
            $data['_natural'] = $ext !== '' ? 'ext:'.$ext : ($pay !== '' ? 'pay:'.$pay : null);
        }

        $nk = $this->naturalKeyValue($entity, $data, $model);
        if ($nk !== null) {
            $data['_natural_key'] = $nk;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function naturalKeyValue(string $entity, array $data, ?Model $model = null): ?string
    {
        $cfg = $this->entityConfig($entity);

        if ($entity === 'mevon_pay_ledger_entry') {
            $n = $data['_natural'] ?? null;
            if (is_string($n) && $n !== '') {
                return $n;
            }
        }

        foreach ([(array) ($cfg['natural_key'] ?? []), (array) ($cfg['fallback_natural_key'] ?? [])] as $keys) {
            if ($keys === []) {
                continue;
            }
            $parts = [];
            $ok = true;
            foreach ($keys as $col) {
                $v = $data[$col] ?? ($model?->{$col} ?? null);
                $v = is_string($v) ? trim($v) : $v;
                if ($v === null || $v === '') {
                    $ok = false;
                    break;
                }
                $parts[] = (string) $v;
            }
            if ($ok && $parts !== []) {
                return strtolower(implode('|', $parts));
            }
        }

        return null;
    }

    /**
     * Probe key for a row (natural key preferred, else origin:{id}).
     */
    public function probeKeyForModel(string $entity, Model $model): string
    {
        $data = $this->serialize($entity, $model);
        $nk = $data['_natural_key'] ?? null;
        if (is_string($nk) && $nk !== '') {
            return $nk;
        }

        return 'origin:'.(int) $model->getKey();
    }

    /**
     * Lightweight probe key (no full serialize) — safe for existence checks only.
     */
    public function probeKeyLightForModel(string $entity, Model $model): string
    {
        if ($entity === 'mevon_pay_ledger_entry') {
            $ext = trim((string) ($model->external_reference ?? ''));
            $pay = trim((string) ($model->payout_reference ?? ''));
            if ($ext !== '') {
                return 'ext:'.$ext;
            }
            if ($pay !== '') {
                return 'pay:'.$pay;
            }
        }

        $attrs = $model->getAttributes();
        $nk = $this->naturalKeyValue($entity, $attrs, $model);
        if ($nk !== null) {
            return $nk;
        }

        return 'origin:'.(int) $model->getKey();
    }

    /**
     * @param  list<string>  $keys
     * @return array{missing: list<string>, present: list<string>}
     */
    public function probe(string $entity, array $keys): array
    {
        $missing = [];
        $present = [];
        foreach ($keys as $key) {
            if ($this->findLocal($entity, ['_probe_key' => $key]) !== null) {
                $present[] = $key;
            } else {
                $missing[] = $key;
            }
        }

        return ['missing' => $missing, 'present' => $present];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsert(string $entity, array $data, string $operation = 'upsert', bool $insertOnly = false): string
    {
        $cfg = $this->entityConfig($entity);
        /** @var class-string<Model> $class */
        $class = $cfg['model'];
        $originId = (int) ($data['_origin_id'] ?? 0);

        if ($operation === 'delete') {
            $local = $this->findLocal($entity, $data);
            if ($local) {
                $local->delete();
            }
            if ($originId > 0) {
                LiveSyncRowMap::query()->where('entity', $entity)->where('origin_id', $originId)->delete();
            }

            return (string) ($data['_natural_key'] ?? $originId);
        }

        $attributes = $this->attributesForWrite($entity, $data);
        $local = $this->findLocal($entity, $data);

        if ($insertOnly && $local) {
            return (string) ($this->naturalKeyValue($entity, $data, $local) ?: $local->getKey());
        }

        if (! $local) {
            $local = new $class;
        }

        // Only fill columns that exist on the table
        $table = (new $class)->getTable();
        $columns = Schema::getColumnListing($table);
        $fill = Arr::only($attributes, $columns);
        unset($fill['id']);

        $local->fill($fill);
        $local->save();

        $nk = $this->naturalKeyValue($entity, $data, $local);
        if ($originId > 0) {
            LiveSyncRowMap::query()->updateOrCreate(
                ['entity' => $entity, 'origin_id' => $originId],
                ['local_id' => (int) $local->getKey(), 'natural_key' => $nk],
            );
        }

        return (string) ($nk ?: $local->getKey());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function findLocal(string $entity, array $data): ?Model
    {
        $cfg = $this->entityConfig($entity);
        /** @var class-string<Model> $class */
        $class = $cfg['model'];

        $probeKey = (string) ($data['_probe_key'] ?? '');
        if ($probeKey !== '') {
            if (str_starts_with($probeKey, 'origin:')) {
                $originId = (int) substr($probeKey, 7);
                $map = LiveSyncRowMap::query()->where('entity', $entity)->where('origin_id', $originId)->first();
                if ($map) {
                    return $class::query()->find($map->local_id);
                }

                return null;
            }

            // natural key probe
            $map = LiveSyncRowMap::query()->where('entity', $entity)->where('natural_key', $probeKey)->first();
            if ($map) {
                return $class::query()->find($map->local_id);
            }

            return $this->findByNaturalKeyColumns($entity, $probeKey);
        }

        $nk = $this->naturalKeyValue($entity, $data);
        if ($nk !== null) {
            $found = $this->findByNaturalKeyColumns($entity, $nk);
            if ($found) {
                return $found;
            }
            $map = LiveSyncRowMap::query()->where('entity', $entity)->where('natural_key', $nk)->first();
            if ($map) {
                return $class::query()->find($map->local_id);
            }
        }

        $originId = (int) ($data['_origin_id'] ?? 0);
        if ($originId > 0) {
            $map = LiveSyncRowMap::query()->where('entity', $entity)->where('origin_id', $originId)->first();
            if ($map) {
                return $class::query()->find($map->local_id);
            }
        }

        return null;
    }

    private function findByNaturalKeyColumns(string $entity, string $naturalKey): ?Model
    {
        $cfg = $this->entityConfig($entity);
        /** @var class-string<Model> $class */
        $class = $cfg['model'];

        if ($entity === 'mevon_pay_ledger_entry') {
            if (str_starts_with($naturalKey, 'ext:')) {
                return $class::query()->where('external_reference', substr($naturalKey, 4))->first();
            }
            if (str_starts_with($naturalKey, 'pay:')) {
                return $class::query()->where('payout_reference', substr($naturalKey, 4))->first();
            }

            return null;
        }

        foreach ([(array) ($cfg['natural_key'] ?? []), (array) ($cfg['fallback_natural_key'] ?? [])] as $cols) {
            if ($cols === [] || $cols === ['id']) {
                continue;
            }
            // single-column natural keys are most common
            if (count($cols) === 1) {
                $col = $cols[0];
                $q = $class::query();
                if ($col === 'email') {
                    $q->where($col, strtolower($naturalKey));
                } else {
                    // try exact then case-insensitive for codes
                    $found = $q->where($col, $naturalKey)->first();
                    if ($found) {
                        return $found;
                    }
                    $found = $class::query()->whereRaw('LOWER('.$col.') = ?', [strtolower($naturalKey)])->first();
                    if ($found) {
                        return $found;
                    }
                }

                return $class::query()->where($col, $naturalKey)->first()
                    ?? ($col === 'email' ? $class::query()->where('email', strtolower($naturalKey))->first() : null);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributesForWrite(string $entity, array $data): array
    {
        $cfg = $this->entityConfig($entity);
        $excludeMeta = ['_origin_id', '_natural_key', '_natural', '_probe_key'];
        foreach (array_keys((array) ($cfg['extras'] ?? [])) as $extra) {
            $excludeMeta[] = $extra;
        }

        $attrs = Arr::except($data, $excludeMeta);

        foreach (array_keys((array) ($cfg['resolve'] ?? [])) as $localCol) {
            unset($attrs[$localCol]);
        }

        foreach ((array) ($cfg['resolve'] ?? []) as $localCol => $rule) {
            $via = (string) ($rule['via'] ?? '');
            $viaVal = $data[$via] ?? null;
            if ($viaVal === null || $viaVal === '') {
                $attrs[$localCol] = null;

                continue;
            }

            if (! empty($rule['origin'])) {
                $map = LiveSyncRowMap::query()
                    ->where('entity', (string) $rule['entity'])
                    ->where('origin_id', (int) $viaVal)
                    ->first();
                $attrs[$localCol] = $map?->local_id;

                continue;
            }

            $targetEntity = (string) $rule['entity'];
            $attr = (string) ($rule['attr'] ?? 'id');
            $targetClass = $this->modelClass($targetEntity);
            $related = $targetClass::query()->where($attr, $viaVal)->first()
                ?? ($attr === 'email' ? $targetClass::query()->where('email', strtolower((string) $viaVal))->first() : null)
                ?? ($attr === 'business_id' ? $targetClass::query()->whereRaw('UPPER(business_id) = ?', [strtoupper((string) $viaVal)])->first() : null);

            $attrs[$localCol] = $related?->getKey();
        }

        // Never overwrite local pin hashes from sync unless explicitly present (we exclude them)
        return $this->normalizeJsonCastAttributes($entity, $attrs);
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return array<string, mixed>
     */
    private function normalizeJsonCastAttributes(string $entity, array $attrs): array
    {
        $class = $this->modelClass($entity);
        $casts = (new $class)->getCasts();

        foreach ($casts as $column => $cast) {
            if (! array_key_exists($column, $attrs)) {
                continue;
            }
            if (! in_array($cast, ['array', 'json', 'object', 'collection'], true)
                && ! (is_string($cast) && str_starts_with($cast, 'AsArray'))) {
                continue;
            }
            if ($class === \App\Models\Payment::class && $column === 'webhook_urls_sent') {
                $attrs[$column] = \App\Models\Payment::decodeJsonList($attrs[$column]);

                continue;
            }
            if (is_string($attrs[$column])) {
                $decoded = json_decode($attrs[$column], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $attrs[$column] = $decoded;
                }
            }
        }

        return $attrs;
    }
}
