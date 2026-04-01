<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\ExternalApi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExternalApiController extends Controller
{
    protected array $serviceOptions = [
        'invoice',
        'membership',
        'rental',
        'ticket_sale',
        'charity',
        'rentals_wallet',
    ];

    public function index(): View
    {
        $externalApis = ExternalApi::with('businesses')->orderBy('name')->get();
        $businesses = Business::where('is_active', true)->orderBy('name')->get();

        $serviceOptions = $this->serviceOptions;
        return view('admin.external-apis.index', compact('externalApis', 'businesses', 'serviceOptions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'provider_key' => 'required|string|max:100|unique:external_apis,provider_key',
            'is_active' => 'nullable|boolean',
        ]);

        ExternalApi::create([
            'name' => $validated['name'],
            'provider_key' => strtolower(trim($validated['provider_key'])),
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('admin.external-apis.index')->with('success', 'External API added.');
    }

    public function updateBusinesses(Request $request, ExternalApi $externalApi): RedirectResponse
    {
        $validated = $request->validate([
            'configs' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $externalApi->update([
            'is_active' => $request->has('is_active'),
        ]);

        $configs = $validated['configs'] ?? [];
        $syncData = [];
        foreach ($configs as $businessId => $config) {
            $enabled = (bool) ($config['enabled'] ?? false);
            if (!$enabled) {
                continue;
            }
            if (!Business::whereKey($businessId)->exists()) {
                continue;
            }
            $mode = $config['mode'] ?? 'hybrid';
            if (!in_array($mode, ['external_only', 'hybrid', 'internal_only'], true)) {
                $mode = 'hybrid';
            }

            $vaGenerationMode = strtolower(trim((string) ($config['va_generation_mode'] ?? 'dynamic')));
            if (!in_array($vaGenerationMode, ['dynamic', 'temp'], true)) {
                $vaGenerationMode = 'dynamic';
            }

            $services = $config['services'] ?? [];
            $services = is_array($services) ? array_values(array_intersect($services, $this->serviceOptions)) : [];

            $syncData[(int) $businessId] = [
                'assignment_mode' => $mode,
                'services' => !empty($services) ? json_encode($services) : null,
                'va_generation_mode' => $vaGenerationMode,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $externalApi->businesses()->sync($syncData);

        if ($externalApi->provider_key === 'mevonpay') {
            Business::query()->update(['uses_external_account_numbers' => false]);
            if (!empty($syncData)) {
                Business::whereIn('id', array_keys($syncData))->update(['uses_external_account_numbers' => true]);
            }
        }

        return redirect()->route('admin.external-apis.index')->with('success', 'External API business list updated.');
    }
}
