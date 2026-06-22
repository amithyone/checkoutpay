<?php

namespace App\Services\Consumer;

use App\Models\ConsumerAppSession;
use App\Models\ConsumerAppSessionEvent;
use App\Models\ConsumerWalletApiAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

class ConsumerAppSessionService
{
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
     * Start a new app session when the user signs in. Server always creates the session UUID.
     */
    public function startLoginSession(
        ConsumerWalletApiAccount $account,
        string $loginMethod,
        Request $request,
        ?int $personalAccessTokenId = null,
    ): string {
        $ctx = $this->clientContextFromRequest($request);
        $uuid = (string) Str::uuid();
        $now = now();

        $session = ConsumerAppSession::query()->create([
            'session_uuid' => $uuid,
            'consumer_wallet_api_account_id' => $account->id,
            'whatsapp_wallet_id' => $account->whatsapp_wallet_id,
            'phone_e164' => $account->phone_e164,
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
            ConsumerAppSessionEvent::TYPE_LOGIN,
            'Signed in via '.$session->loginMethodLabel(),
            $request,
            ['login_method' => $loginMethod],
        );

        $account->forceFill(['last_app_active_at' => $now])->save();

        return $uuid;
    }

    /**
     * Call immediately after issuing a Sanctum token on login/register.
     */
    public function afterTokenIssued(
        ConsumerWalletApiAccount $account,
        string $loginMethod,
        Request $request,
        NewAccessToken $accessToken,
    ): string {
        return $this->startLoginSession(
            $account,
            $loginMethod,
            $request,
            (int) $accessToken->accessToken->id,
        );
    }

    public function afterPlainTokenIssued(
        ConsumerWalletApiAccount $account,
        string $loginMethod,
        Request $request,
    ): string {
        $tokenModel = $account->tokens()->latest('id')->first();

        return $this->startLoginSession(
            $account,
            $loginMethod,
            $request,
            $tokenModel?->id !== null ? (int) $tokenModel->id : null,
        );
    }

    public function endSession(Request $request, ?ConsumerWalletApiAccount $account = null): void
    {
        $session = $this->resolveSession($request, $account);
        if ($session === null || ! $session->isActive()) {
            return;
        }

        $session->ended_at = now();
        $session->save();

        $this->recordEvent(
            $session,
            ConsumerAppSessionEvent::TYPE_LOGOUT,
            'Signed out',
            $request,
        );
    }

    public function touchSession(Request $request, ?ConsumerWalletApiAccount $account = null): void
    {
        $session = $this->resolveSession($request, $account);
        if ($session === null || ! $session->isActive()) {
            return;
        }

        $now = now();
        $session->last_seen_at = $now;
        $session->save();

        if ($account !== null) {
            $account->forceFill(['last_app_active_at' => $now])->save();
        }
    }

    public function recordForAccount(
        ConsumerWalletApiAccount $account,
        Request $request,
        string $eventType,
        string $summary,
        ?array $meta = null,
    ): void {
        $session = $this->resolveSession($request, $account);
        $this->recordEvent($session, $eventType, $summary, $request, $meta, $account);
    }

    public function resolveSession(Request $request, ?ConsumerWalletApiAccount $account = null): ?ConsumerAppSession
    {
        $uuid = $this->sessionUuidFromRequest($request);
        if ($uuid !== null) {
            $byUuid = ConsumerAppSession::query()->where('session_uuid', $uuid)->first();
            if ($byUuid !== null) {
                return $byUuid;
            }
        }

        if ($account === null) {
            return null;
        }

        $token = $account->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $byToken = ConsumerAppSession::query()
                ->where('personal_access_token_id', $token->id)
                ->whereNull('ended_at')
                ->orderByDesc('id')
                ->first();
            if ($byToken !== null) {
                return $byToken;
            }
        }

        return ConsumerAppSession::query()
            ->where('consumer_wallet_api_account_id', $account->id)
            ->whereNull('ended_at')
            ->orderByDesc('id')
            ->first();
    }

    private function recordEvent(
        ?ConsumerAppSession $session,
        string $eventType,
        string $summary,
        Request $request,
        ?array $meta = null,
        ?ConsumerWalletApiAccount $account = null,
    ): void {
        ConsumerAppSessionEvent::query()->create([
            'consumer_app_session_id' => $session?->id,
            'consumer_wallet_api_account_id' => $session?->consumer_wallet_api_account_id ?? $account?->id,
            'whatsapp_wallet_id' => $session?->whatsapp_wallet_id ?? $account?->whatsapp_wallet_id,
            'phone_e164' => $session?->phone_e164 ?? $account?->phone_e164,
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
