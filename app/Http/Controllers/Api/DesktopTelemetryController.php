<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DesktopAppToken;
use App\Models\DesktopPolicy;
use App\Models\DesktopTelemetryEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DesktopTelemetryController extends Controller
{
    /**
     * POST /api/desktop/events/batch
     *
     * Headers:
     *   Authorization: Bearer <token>
     *   X-Amithy-Signature: <hex hmac sha256 of raw body>
     *
     * JSON body:
     *   { "appRole": "admin|player", "appInstanceId": "...", "sentAt": "ISO", "events": [...] }
     */
    public function ingestBatch(Request $request): JsonResponse
    {
        $auth = $this->authorize($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }
        [$tenantId, $token] = $auth;

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

        $accepted = 0;
        $duplicates = 0;
        $now = now();

        foreach ($payload['events'] as $event) {
            try {
                DesktopTelemetryEvent::create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'app_role' => $payload['appRole'],
                    'app_instance_id' => $payload['appInstanceId'],
                    'event_id' => $event['id'],
                    'event_type' => $event['type'],
                    'event_ts' => Carbon::parse($event['ts']),
                    'app_version' => $event['appVersion'] ?? null,
                    'payload_json' => $event['payload'] ?? [],
                    'context_json' => $event['context'] ?? [],
                    'received_at' => $now,
                ]);
                $accepted++;
            } catch (\Illuminate\Database\QueryException $e) {
                if ((int) $e->getCode() === 23000) {
                    $duplicates++;
                    continue;
                }
                Log::error('Desktop telemetry insert failed', [
                    'tenant_id' => $tenantId,
                    'app_instance_id' => $payload['appInstanceId'],
                    'event_id' => $event['id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        if ($token) {
            $token->forceFill(['last_seen_at' => $now])->saveQuietly();
        }

        $policy = $this->loadPolicyFor($tenantId, $payload['appRole'], $payload['appInstanceId']);

        return response()->json([
            'ok' => true,
            'accepted' => $accepted,
            'duplicates' => $duplicates,
            'policy' => $policy,
        ]);
    }

    /**
     * GET /api/desktop/policy?role=admin|player&instance=<id>
     */
    public function getPolicy(Request $request): JsonResponse
    {
        $auth = $this->authorize($request, requireSignature: false);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }
        [$tenantId] = $auth;

        $data = $request->validate([
            'role' => ['required', 'string', 'in:admin,player'],
            'instance' => ['required', 'string', 'max:128'],
        ]);

        return response()->json($this->loadPolicyFor($tenantId, $data['role'], $data['instance']));
    }

    /**
     * @return JsonResponse|array{0:string,1:?DesktopAppToken}
     */
    protected function authorize(Request $request, bool $requireSignature = true): JsonResponse|array
    {
        $bearer = trim((string) $request->bearerToken());
        if ($bearer === '') {
            return response()->json(['ok' => false, 'message' => 'Missing bearer token.'], 401);
        }

        $tokenRow = DesktopAppToken::query()
            ->where('bearer_token', $bearer)
            ->where('is_active', true)
            ->first();

        if ($tokenRow) {
            $tenantId = $tokenRow->tenant_id ?: 'default-tenant';
            $secret = (string) $tokenRow->hmac_secret;
        } else {
            $envToken = (string) env('AMITHY_API_TOKEN', '');
            if ($envToken === '' || ! hash_equals($envToken, $bearer)) {
                return response()->json(['ok' => false, 'message' => 'Invalid bearer token.'], 401);
            }
            $tenantId = (string) env('AMITHY_API_TENANT', 'default-tenant');
            $secret = (string) env('AMITHY_API_SECRET', $envToken);
        }

        if ($requireSignature) {
            $rawBody = $request->getContent();
            $providedSig = (string) $request->header('x-amithy-signature', '');
            if ($providedSig === '' || $secret === '') {
                return response()->json(['ok' => false, 'message' => 'Missing signature.'], 401);
            }
            $expectedSig = hash_hmac('sha256', $rawBody, $secret);
            if (! hash_equals($expectedSig, $providedSig)) {
                return response()->json(['ok' => false, 'message' => 'Invalid signature.'], 401);
            }
        }

        return [$tenantId, $tokenRow];
    }

    protected function loadPolicyFor(string $tenantId, string $role, string $instance): array
    {
        $policy = DesktopPolicy::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($role, $instance) {
                $q->where(function ($q2) use ($instance) {
                    $q2->where('scope_type', DesktopPolicy::SCOPE_INSTANCE)
                        ->where('scope_value', $instance);
                })->orWhere(function ($q2) use ($role) {
                    $q2->where('scope_type', DesktopPolicy::SCOPE_ROLE)
                        ->where('scope_value', $role);
                })->orWhere(function ($q2) {
                    $q2->where('scope_type', DesktopPolicy::SCOPE_GLOBAL)
                        ->where('scope_value', '*');
                });
            })
            ->orderByRaw("CASE scope_type WHEN 'instance' THEN 1 WHEN 'role' THEN 2 ELSE 3 END")
            ->first();

        if (! $policy) {
            return [
                'locked' => false,
                'lockReasonCode' => null,
                'lockAt' => null,
                'graceUntil' => null,
                'minHeartbeatSeconds' => 300,
            ];
        }

        return $policy->toApiPayload();
    }
}
