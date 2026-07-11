<?php

namespace App\Http\Middleware;

use App\Services\Security\AdminHoneypotService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trap requests to the decoy admin path (default: /admin).
 */
class AdminHoneypotTrap
{
    public function __construct(private AdminHoneypotService $honeypot) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->honeypot->recordHit($request);

        return $this->honeypot->refuseResponse();
    }
}
