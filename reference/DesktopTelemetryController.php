<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DesktopTelemetryController extends Controller
{
    /**
     * POST /api/desktop/events/batch
     *
     * Expected headers:
     * - Authorization: Bearer <token>
     * - X-Amithy-Signature: <hex hmac sha256 of raw body>
     *
     * Expected JSON body:
     * {
     *   "appRole": "admin|player",
     *   "appInstanceId": "string",
     *   "sentAt": "ISO date",
     *   "events": [ ... ]
     * }
     */
    public function ingestBatch(Request $request)
    {
        // Optional HMAC verification (recommended).
        $rawBody = $request->getContent();
        $providedSig = (string) $request->header('x-amithy-signature', '');
        $sharedSecret = (string) env('AMITHY_API_TOKEN', '');
        if ($sharedSecret !== '') {
            $expectedSig = hash_hmac('sha256', $rawBody, $sharedSecret);
            if (!hash_equals($expectedSig, $providedSig)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Invalid signature',
                ], 401);
            }
        }

        $payload = $request->validate([
            'appRole' => ['required', 'string', 'in:admin,player'],
            'appInstanceId' => ['required', 'string', 'max:128'],
            'sentAt' => ['required', 'date'],
            'events' => ['required', 'array', 'max:200'],
            'events.*.id' => ['required', 'string', 'max:64'],
            'events.*.type' => ['required', 'string', 'max:80'],
            'events.*.ts' => ['required', 'date'],
            'events.*.appVersion' => ['nullable', 'string', 'max:64'],
            'events.*.payload' => ['nullable', 'array'],
            'events.*.context' => ['nullable', 'array'],
        ]);

        $tenantId = $this->resolveTenantIdFromToken($request);
        $insertRows = [];

        foreach ($payload['events'] as $event) {
            $insertRows[] = [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'app_role' => $payload['appRole'],
                'app_instance_id' => $payload['appInstanceId'],
                'event_id' => $event['id'],
                'event_type' => $event['type'],
                'event_ts' => $event['ts'],
                'app_version' => $event['appVersion'] ?? null,
                'payload_json' => json_encode($event['payload'] ?? []),
                'context_json' => json_encode($event['context'] ?? []),
                'received_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Idempotent insert (skip duplicates by event_id + app_instance_id if you add unique index).
        DB::table('desktop_telemetry_events')->insert($insertRows);

        // Policy can be returned inline to reduce extra round trip.
        $policy = $this->loadPolicyFor($tenantId, $payload['appRole'], $payload['appInstanceId']);

        return response()->json([
            'ok' => true,
            'accepted' => count($insertRows),
            'policy' => $policy,
        ]);
    }

    /**
     * GET /api/desktop/policy?role=admin|player&instance=<id>
     */
    public function getPolicy(Request $request)
    {
        $data = $request->validate([
            'role' => ['required', 'string', 'in:admin,player'],
            'instance' => ['required', 'string', 'max:128'],
        ]);

        $tenantId = $this->resolveTenantIdFromToken($request);
        $policy = $this->loadPolicyFor($tenantId, $data['role'], $data['instance']);

        return response()->json($policy);
    }

    /**
     * Load effective policy. You can expand this logic to support:
     * - per app role policy
     * - per app instance policy
     * - fallback tenant/global policy
     */
    protected function loadPolicyFor(string $tenantId, string $role, string $instance): array
    {
        $row = DB::table('desktop_policies')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($role, $instance) {
                $q->where(function ($q2) use ($role, $instance) {
                    $q2->where('scope_type', 'instance')
                        ->where('scope_value', $instance);
                })->orWhere(function ($q2) use ($role) {
                    $q2->where('scope_type', 'role')
                        ->where('scope_value', $role);
                })->orWhere(function ($q2) {
                    $q2->where('scope_type', 'global')
                        ->where('scope_value', '*');
                });
            })
            ->orderByRaw("CASE scope_type WHEN 'instance' THEN 1 WHEN 'role' THEN 2 ELSE 3 END")
            ->first();

        if (!$row) {
            return [
                'locked' => false,
                'lockReasonCode' => null,
                'lockAt' => null,
                'graceUntil' => null,
                'minHeartbeatSeconds' => 300,
            ];
        }

        return [
            'locked' => (bool) $row->locked,
            'lockReasonCode' => $row->lock_reason_code ?: null,
            'lockAt' => $row->lock_at ?: null,
            'graceUntil' => $row->grace_until ?: null,
            'minHeartbeatSeconds' => (int) ($row->min_heartbeat_seconds ?? 300),
        ];
    }

    /**
     * Replace this with your actual tenant/token resolution logic.
     */
    protected function resolveTenantIdFromToken(Request $request): string
    {
        // Example: parse bearer token to tenant.
        // For now, single-tenant fallback.
        return 'default-tenant';
    }
}

