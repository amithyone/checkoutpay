<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuse probes for .env, .error, .git, vendor dumps, and similar sensitive paths.
 */
class BlockSensitivePathProbes
{
    /**
     * @var list<string>
     */
    private array $patterns = [
        '#(^|/)\.env(\.|$)#i',
        '#(^|/)\.error(\.|$)#i',
        '#(^|/)\.git(/|$)#i',
        '#(^|/)\.svn(/|$)#i',
        '#(^|/)\.hg(/|$)#i',
        '#(^|/)composer\.(json|lock)$#i',
        '#(^|/)package(-lock)?\.json$#i',
        '#(^|/)artisan$#i',
        '#(^|/)phpunit\.xml#i',
        '#(^|/)webpack\.mix\.js$#i',
        '#(^|/)vite\.config\.#i',
        '#(^|/)storage/logs/#i',
        '#(^|/)storage/oauth-#i',
        '#(^|/)vendor/#i',
        '#(^|/)\.aws/#i',
        '#(^|/)id_rsa#i',
        '#(^|/)\.htpasswd$#i',
        '#(^|/)test_extraction\.php$#i',
        '#(^|/)test_connection\.php$#i',
        '#(^|/)x7f3\.php$#i',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $path = ltrim($request->path(), '/');

        foreach ($this->patterns as $pattern) {
            if (preg_match($pattern, $path) === 1) {
                Log::channel('honeypot')->warning('sensitive_path_probe', [
                    'ip' => $request->ip(),
                    'method' => $request->method(),
                    'path' => '/'.$path,
                    'ua' => substr((string) $request->userAgent(), 0, 300),
                ]);

                abort(404);
            }
        }

        return $next($request);
    }
}
