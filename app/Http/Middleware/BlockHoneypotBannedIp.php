<?php

namespace App\Http\Middleware;

use App\Services\Security\AdminHoneypotService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Block IPs banned after hitting the /admin honeypot (and similar traps).
 */
class BlockHoneypotBannedIp
{
    public function __construct(private AdminHoneypotService $honeypot) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->honeypot->isBanned($request->ip())) {
            return $this->honeypot->refuseResponse();
        }

        return $next($request);
    }
}
