<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use App\Support\AdminPath;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            $request = request();

            if ($e instanceof TokenMismatchException) {
                Log::warning('security.csrf_token_mismatch', [
                    'path' => $request?->path(),
                    'method' => $request?->method(),
                    'ip' => $request?->ip(),
                    'user_id' => optional($request?->user())->id,
                    'business_id' => optional($request?->user('business'))->id,
                    'session_id' => $request?->session()?->getId(),
                ]);
                return;
            }

            if ($e instanceof AuthenticationException) {
                Log::warning('security.authentication_exception', [
                    'path' => $request?->path(),
                    'method' => $request?->method(),
                    'ip' => $request?->ip(),
                    'guards' => method_exists($e, 'guards') ? $e->guards() : [],
                    'session_id' => $request?->session()?->getId(),
                ]);
                return;
            }

            if ($e instanceof HttpExceptionInterface && $e->getStatusCode() === 403) {
                Log::warning('security.http_403_forbidden', [
                    'path' => $request?->path(),
                    'method' => $request?->method(),
                    'ip' => $request?->ip(),
                    'user_id' => optional($request?->user())->id,
                    'business_id' => optional($request?->user('business'))->id,
                    'message' => $e->getMessage(),
                ]);
            }
        });

        $this->renderable(function (TokenMismatchException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your session expired. Refresh the page and try again.',
                    'csrf_token' => csrf_token(),
                ], 419);
            }

            if (! $this->shouldSoftRecoverCsrf($request)) {
                return null;
            }

            return redirect()
                ->to($this->csrfRecoverUrl($request))
                ->withInput($request->except($this->dontFlash))
                ->with('error', 'Your session refreshed. Please submit the form again.');
        });
    }

    private function shouldSoftRecoverCsrf(Request $request): bool
    {
        return AdminPath::requestIsAdminPanel($request)
            || $request->is('investor', 'investor/*');
    }

    private function csrfRecoverUrl(Request $request): string
    {
        if ($request->is('investor/access/*') && $request->isMethod('POST')) {
            return $request->url();
        }

        $referer = $request->headers->get('referer');
        if (is_string($referer) && $referer !== '') {
            return $referer;
        }

        if (AdminPath::requestIsAdminPanel($request)) {
            return url(AdminPath::urlPrefix());
        }

        if ($request->is('investor', 'investor/*')) {
            return route('investor.gate.lookup');
        }

        return url('/');
    }
}
