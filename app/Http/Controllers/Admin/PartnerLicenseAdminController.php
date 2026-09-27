<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PartnerLicense;
use App\Services\Partner\PartnerLicenseIssuerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PartnerLicenseAdminController extends Controller
{
    public function __construct(
        private PartnerLicenseIssuerService $issuer,
    ) {}

    public function index(): View
    {
        $licenses = PartnerLicense::query()->orderByDesc('id')->get();
        $issuerEnabled = $this->issuer->isEnabled();

        return view('admin.partner-licenses.index', compact('licenses', 'issuerEnabled'));
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $this->issuer->isEnabled()) {
            return redirect()->route('admin.partner-licenses.index')
                ->with('error', 'Enable PARTNER_LICENSE_ISSUER_ENABLED on this server before issuing keys.');
        }

        $validated = $request->validate([
            'partner_name' => 'required|string|max:255',
            'allowed_hosts' => 'nullable|string|max:2000',
            'valid_until' => 'nullable|date',
            'min_build_version' => 'nullable|string|max:32',
            'notes' => 'nullable|string|max:2000',
        ]);

        $hosts = [];
        if (! empty($validated['allowed_hosts'])) {
            $hosts = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', (string) $validated['allowed_hosts']))));
        }

        $license = $this->issuer->create([
            'partner_name' => $validated['partner_name'],
            'allowed_hosts' => $hosts,
            'valid_until' => $validated['valid_until'] ?? null,
            'min_build_version' => $validated['min_build_version'] ?? '1.0.0',
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('admin.partner-licenses.index')
            ->with('success', 'License created. Key: '.$license->license_key);
    }

    public function revoke(PartnerLicense $partnerLicense): RedirectResponse
    {
        $this->issuer->revoke($partnerLicense);

        return redirect()->route('admin.partner-licenses.index')
            ->with('success', 'License revoked for '.$partnerLicense->partner_name);
    }
}
