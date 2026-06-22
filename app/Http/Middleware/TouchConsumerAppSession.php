<?php

namespace App\Http\Middleware;

use App\Models\ConsumerWalletApiAccount;
use App\Services\Consumer\ConsumerAppSessionService;
use Closure;
use Illuminate\Http\Request;
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
            $this->sessions->touchSession($request, $user);
        }

        return $next($request);
    }
}
