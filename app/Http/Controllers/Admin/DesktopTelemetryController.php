<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DesktopAppToken;
use App\Models\DesktopPolicy;
use App\Models\DesktopTelemetryEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DesktopTelemetryController extends Controller
{
    public function eventsIndex(Request $request): View
    {
        $query = DesktopTelemetryEvent::query()->latest('event_ts');

        if ($q = $request->string('q')->toString()) {
            $query->where(function ($w) use ($q) {
                $w->where('app_instance_id', 'like', "%{$q}%")
                    ->orWhere('event_id', 'like', "%{$q}%")
                    ->orWhere('event_type', 'like', "%{$q}%")
                    ->orWhere('tenant_id', 'like', "%{$q}%");
            });
        }
        if ($role = $request->string('role')->toString()) {
            $query->where('app_role', $role);
        }
        if ($type = $request->string('type')->toString()) {
            $query->where('event_type', $type);
        }
        if ($tenant = $request->string('tenant_id')->toString()) {
            $query->where('tenant_id', $tenant);
        }

        $events = $query->paginate(50)->withQueryString();

        $stats = [
            'total' => DesktopTelemetryEvent::count(),
            'last_24h' => DesktopTelemetryEvent::where('received_at', '>=', now()->subDay())->count(),
            'unique_instances' => DesktopTelemetryEvent::distinct('app_instance_id')->count('app_instance_id'),
            'last_event_at' => DesktopTelemetryEvent::max('event_ts'),
        ];

        return view('admin.desktop-telemetry.events-index', compact('events', 'stats'));
    }

    public function eventShow(DesktopTelemetryEvent $event): View
    {
        return view('admin.desktop-telemetry.event-show', compact('event'));
    }

    public function policiesIndex(): View
    {
        $policies = DesktopPolicy::query()->latest('updated_at')->paginate(50);

        return view('admin.desktop-telemetry.policies-index', compact('policies'));
    }

    public function policyEdit(?DesktopPolicy $policy = null): View
    {
        $policy = $policy ?: new DesktopPolicy(['tenant_id' => 'default-tenant', 'min_heartbeat_seconds' => 300]);

        return view('admin.desktop-telemetry.policy-edit', compact('policy'));
    }

    public function policyStore(Request $request): RedirectResponse
    {
        $data = $this->validatePolicy($request);

        $policy = DesktopPolicy::updateOrCreate(
            [
                'tenant_id' => $data['tenant_id'],
                'scope_type' => $data['scope_type'],
                'scope_value' => $data['scope_value'],
            ],
            $data
        );

        return redirect()->route('admin.desktop-telemetry.policies.edit', $policy)
            ->with('success', 'Policy saved.');
    }

    public function policyUpdate(Request $request, DesktopPolicy $policy): RedirectResponse
    {
        $data = $this->validatePolicy($request);
        $policy->update($data);

        return redirect()->route('admin.desktop-telemetry.policies.edit', $policy)
            ->with('success', 'Policy updated.');
    }

    public function policyDestroy(DesktopPolicy $policy): RedirectResponse
    {
        $policy->delete();

        return redirect()->route('admin.desktop-telemetry.policies.index')
            ->with('success', 'Policy deleted.');
    }

    public function tokensIndex(): View
    {
        $tokens = DesktopAppToken::query()->latest('id')->paginate(50);

        return view('admin.desktop-telemetry.tokens-index', compact('tokens'));
    }

    public function tokenStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'tenant_id' => ['required', 'string', 'max:64'],
            'is_active' => ['nullable', 'boolean'],
            'admin_notes' => ['nullable', 'string'],
        ]);

        $bearer = Str::random(48);
        $secret = Str::random(64);

        $token = DesktopAppToken::create([
            'name' => $data['name'],
            'tenant_id' => $data['tenant_id'],
            'bearer_token' => $bearer,
            'hmac_secret' => $secret,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'admin_notes' => $data['admin_notes'] ?? null,
        ]);

        return redirect()->route('admin.desktop-telemetry.tokens.index')
            ->with('success', "Token created for {$token->name}.")
            ->with('new_token_bearer', $bearer)
            ->with('new_token_secret', $secret)
            ->with('new_token_id', $token->id);
    }

    public function tokenRotate(DesktopAppToken $token): RedirectResponse
    {
        $bearer = Str::random(48);
        $secret = Str::random(64);
        $token->update([
            'bearer_token' => $bearer,
            'hmac_secret' => $secret,
        ]);

        return redirect()->route('admin.desktop-telemetry.tokens.index')
            ->with('success', "Rotated credentials for {$token->name}.")
            ->with('new_token_bearer', $bearer)
            ->with('new_token_secret', $secret)
            ->with('new_token_id', $token->id);
    }

    public function tokenToggle(DesktopAppToken $token): RedirectResponse
    {
        $token->update(['is_active' => ! $token->is_active]);

        return back()->with('success', $token->is_active ? 'Token enabled.' : 'Token disabled.');
    }

    public function tokenDestroy(DesktopAppToken $token): RedirectResponse
    {
        $token->delete();

        return redirect()->route('admin.desktop-telemetry.tokens.index')
            ->with('success', 'Token deleted.');
    }

    private function validatePolicy(Request $request): array
    {
        return $request->validate([
            'tenant_id' => ['required', 'string', 'max:64'],
            'scope_type' => ['required', 'string', 'in:instance,role,global'],
            'scope_value' => ['required', 'string', 'max:128'],
            'locked' => ['nullable', 'boolean'],
            'lock_reason_code' => ['nullable', 'string', 'max:80'],
            'lock_at' => ['nullable', 'date'],
            'grace_until' => ['nullable', 'date'],
            'min_heartbeat_seconds' => ['required', 'integer', 'min:30', 'max:86400'],
            'admin_notes' => ['nullable', 'string'],
        ]);
    }
}
