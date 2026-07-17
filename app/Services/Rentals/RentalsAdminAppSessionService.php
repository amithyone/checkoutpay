<?php

namespace App\Services\Rentals;

use App\Models\Admin;
use App\Models\RentalsAdminAppSession;
use App\Models\RentalsAdminAppSessionEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

class RentalsAdminAppSessionService
{
    public function idleTimeoutMinutes(): int
    {
        return max(1, (int) config('rentals_admin.app_session_idle_minutes', 480));
    }

    public function tokenName(): string
    {
        return (string) config('rentals_admin.token_name', 'rentals-admin');
    }

    public function createAccessToken(Admin $admin): NewAccessToken
    {
        return $admin->createToken(
            $this->tokenName(),
            ['*'],
            now()->addMinutes($this->idleTimeoutMinutes()),
        );
    }

    public function isSessionIdleExpired(RentalsAdminAppSession $session): bool
    {
        if (! $session->isActive()) {
            return true;
        }

        $lastSeen = $session->last_seen_at ?? $session->started_at;
        if ($lastSeen === null) {
            return false;
        }

        return $lastSeen->lt(now()->subMinutes($this->idleTimeoutMinutes()));
    }

    public function isAccessTokenIdleExpired(PersonalAccessToken $token): bool
    {
        if ($token->expires_at !== null && $token->expires_at->isPast()) {
            return true;
        }

        $reference = $token->last_used_at ?? $token->created_at;
        if ($reference === null) {
            return false;
        }

        return $reference->lt(now()->subMinutes($this->idleTimeoutMinutes()));
    }

    public function expireDueToIdle(
        Request $request,
        Admin $admin,
        ?RentalsAdminAppSession $session = null,
    ): void {
        $session ??= $this->resolveSession($request, $admin);

        if ($session !== null && $session->isActive()) {
            $session->ended_at = now();
            $session->save();

            $this->recordEvent(
                $session,
                RentalsAdminAppSessionEvent::TYPE_SESSION_EXPIRED,
                'Session expired after '.$this->idleTimeoutMinutes().' minutes of inactivity',
                $request,
                ['reason' => 'idle_timeout'],
            );
        }

        $token = $admin->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }

    /**
     * @return array{platform: ?string, app_version: ?string, device_label: ?string}
     */
    public function clientContextFromRequest(Request $request): array
    {
        $ctx = $request->input('client_context');
        if (! is_array($ctx)) {
            $ctx = [];
        }

        return [
            'platform' => $this->normalizePlatform(
                (string) ($ctx['platform'] ?? $request->header('X-App-Platform', ''))
            ),
            'app_version' => $this->trimNullable((string) ($ctx['app_version'] ?? $request->header('X-App-Version', ''))),
            'device_label' => $this->trimNullable((string) ($ctx['device_label'] ?? $request->header('X-Device-Label', ''))),
        ];
    }

    public function sessionUuidFromRequest(Request $request): ?string
    {
        $raw = (string) ($request->header('X-App-Session-Id') ?: $request->input('app_session_id', ''));

        return Str::isUuid($raw) ? $raw : null;
    }

    /**
     * End any still-active sessions for this admin (e.g. before issuing a replacement token).
     */
    public function endAllActiveForAdmin(Admin $admin, Request $request, string $reason = 'replaced_by_new_login'): void
    {
        $active = RentalsAdminAppSession::query()
            ->where('admin_id', $admin->id)
            ->whereNull('ended_at')
            ->get();

        foreach ($active as $session) {
            $session->ended_at = now();
            $session->save();

            $this->recordEvent(
                $session,
                RentalsAdminAppSessionEvent::TYPE_LOGOUT,
                'Session ended ('.$reason.')',
                $request,
                ['reason' => $reason],
            );
        }
    }

    public function startLoginSession(
        Admin $admin,
        string $loginMethod,
        Request $request,
        ?int $personalAccessTokenId = null,
    ): string {
        $ctx = $this->clientContextFromRequest($request);
        $uuid = (string) Str::uuid();
        $now = now();

        $session = RentalsAdminAppSession::query()->create([
            'session_uuid' => $uuid,
            'admin_id' => $admin->id,
            'admin_email' => $admin->email,
            'admin_name' => $admin->name,
            'login_method' => $loginMethod,
            'platform' => $ctx['platform'],
            'app_version' => $ctx['app_version'],
            'device_label' => $ctx['device_label'],
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'personal_access_token_id' => $personalAccessTokenId,
            'started_at' => $now,
            'last_seen_at' => $now,
        ]);

        $this->recordEvent(
            $session,
            RentalsAdminAppSessionEvent::TYPE_LOGIN,
            'Signed in via '.$session->loginMethodLabel(),
            $request,
            ['login_method' => $loginMethod],
        );

        return $uuid;
    }

    public function afterTokenIssued(
        Admin $admin,
        string $loginMethod,
        Request $request,
        NewAccessToken $accessToken,
    ): string {
        return $this->startLoginSession(
            $admin,
            $loginMethod,
            $request,
            (int) $accessToken->accessToken->id,
        );
    }

    public function endSession(Request $request, ?Admin $admin = null): void
    {
        $session = $this->resolveSession($request, $admin);
        if ($session === null || ! $session->isActive()) {
            return;
        }

        $session->ended_at = now();
        $session->save();

        $this->recordEvent(
            $session,
            RentalsAdminAppSessionEvent::TYPE_LOGOUT,
            'Signed out',
            $request,
        );
    }

    public function touchSession(Request $request, ?Admin $admin = null): void
    {
        $session = $this->resolveSession($request, $admin);
        if ($session === null || ! $session->isActive()) {
            return;
        }

        $session->last_seen_at = now();
        $session->save();
    }

    public function resolveSession(Request $request, ?Admin $admin = null): ?RentalsAdminAppSession
    {
        $uuid = $this->sessionUuidFromRequest($request);
        if ($uuid !== null) {
            $byUuid = RentalsAdminAppSession::query()->where('session_uuid', $uuid)->first();
            if ($byUuid !== null) {
                return $byUuid;
            }
        }

        if ($admin === null) {
            return null;
        }

        $token = $admin->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $byToken = RentalsAdminAppSession::query()
                ->where('personal_access_token_id', $token->id)
                ->whereNull('ended_at')
                ->orderByDesc('id')
                ->first();
            if ($byToken !== null) {
                return $byToken;
            }
        }

        return RentalsAdminAppSession::query()
            ->where('admin_id', $admin->id)
            ->whereNull('ended_at')
            ->orderByDesc('id')
            ->first();
    }

    private function recordEvent(
        ?RentalsAdminAppSession $session,
        string $eventType,
        string $summary,
        Request $request,
        ?array $meta = null,
        ?Admin $admin = null,
    ): void {
        RentalsAdminAppSessionEvent::query()->create([
            'rentals_admin_app_session_id' => $session?->id,
            'admin_id' => $session?->admin_id ?? $admin?->id,
            'admin_email' => $session?->admin_email ?? $admin?->email,
            'event_type' => $eventType,
            'summary' => Str::limit($summary, 255, ''),
            'meta' => $meta,
            'ip_address' => $request->ip(),
        ]);
    }

    private function normalizePlatform(string $value): ?string
    {
        $v = strtolower(trim($value));
        if (in_array($v, ['ios', 'android', 'web'], true)) {
            return $v;
        }

        return $v !== '' ? Str::limit($v, 16, '') : null;
    }

    private function trimNullable(string $value): ?string
    {
        $v = trim($value);

        return $v !== '' ? Str::limit($v, 160, '') : null;
    }
}
