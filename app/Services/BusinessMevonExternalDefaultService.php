<?php

namespace App\Services;

use App\Models\Business;
use App\Models\ExternalApi;
use Illuminate\Support\Facades\Schema;

/**
 * Default checkout VAs: MevonPay temporary accounts (createtempva), not the internal pool.
 */
class BusinessMevonExternalDefaultService
{
    public const ASSIGNMENT_MODE = 'external_only';

    public const VA_GENERATION_MODE = 'temp';

    public function ensureAssigned(Business $business): void
    {
        $api = $this->mevonApi();
        if (! $api) {
            return;
        }

        if ($business->externalApis()->where('external_apis.id', $api->id)->exists()) {
            if (! $business->uses_external_account_numbers) {
                $business->forceFill(['uses_external_account_numbers' => true])->saveQuietly();
            }

            return;
        }

        $business->externalApis()->attach($api->id, [
            'assignment_mode' => self::ASSIGNMENT_MODE,
            'va_generation_mode' => self::VA_GENERATION_MODE,
            'services' => null,
        ]);
        $business->unsetRelation('externalApis');
        $business->forceFill(['uses_external_account_numbers' => true])->saveQuietly();
    }

    public function syncFromFlag(Business $business, bool $useExternal): void
    {
        if ($useExternal) {
            $this->ensureAssigned($business);

            return;
        }

        $api = $this->mevonApi();
        if ($api) {
            $business->externalApis()->detach($api->id);
            $business->unsetRelation('externalApis');
        }
        $business->forceFill(['uses_external_account_numbers' => false])->saveQuietly();
    }

    public function backfillMissing(): int
    {
        $api = $this->mevonApi();
        if (! $api) {
            return 0;
        }

        $assignedIds = $api->businesses()->pluck('businesses.id');
        $missing = Business::query()->whereNotIn('id', $assignedIds)->get();
        foreach ($missing as $business) {
            $this->ensureAssigned($business);
        }

        return $missing->count();
    }

    /**
     * Point existing Mevon assignments at temp VAs unless admin set internal-only.
     */
    public function normalizeAssignedToTempExternal(): int
    {
        $api = $this->mevonApi();
        if (! $api) {
            return 0;
        }

        $count = 0;
        foreach ($api->businesses()->get() as $business) {
            $mode = (string) ($business->pivot->assignment_mode ?? '');
            if ($mode === 'internal_only') {
                continue;
            }

            $api->businesses()->updateExistingPivot($business->id, [
                'assignment_mode' => self::ASSIGNMENT_MODE,
                'va_generation_mode' => self::VA_GENERATION_MODE,
            ]);
            $business->forceFill(['uses_external_account_numbers' => true])->saveQuietly();
            $count++;
        }

        return $count;
    }

    private function mevonApi(): ?ExternalApi
    {
        if (! Schema::hasTable('external_apis') || ! Schema::hasTable('business_external_api')) {
            return null;
        }

        return ExternalApi::query()->firstOrCreate(
            ['provider_key' => 'mevonpay'],
            ['name' => 'MevonPay', 'is_active' => true],
        );
    }
}
