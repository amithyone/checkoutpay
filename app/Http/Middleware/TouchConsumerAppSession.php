<?php

namespace App\Http\Middleware;

use App\Models\ConsumerWalletApiAccount;
use App\Services\Consumer\ConsumerAppSessionService;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class TouchConsumerAppSession
{
    public function __construct(
        private ConsumerAppSessionService $sessions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof ConsumerWalletApiAccount) {
            $session = $this->sessions->resolveSession($request, $user);
            $token = $user->currentAccessToken();

            $idleExpired = ($session !== null && $this->sessions->isSessionIdleExpired($session))
                || ($token instanceof PersonalAccessToken && $this->sessions->isAccessTokenIdleExpired($token));

            if ($idleExpired) {
                $this->sessions->expireDueToIdle($request, $user, $session);

                return response()->json([
                    'success' => false,
                    'message' => 'Session expired. Please sign in again.',
                    'code' => 'session_expired',
                ], 401);
            }

            $this->sessions->touchSession($request, $user);
        }

        return $next($request);
    }
}
