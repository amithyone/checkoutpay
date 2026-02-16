<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Authenticate extends Middleware
{
    /**
     * Handle an incoming request.
     * 
     * Override to allow admin impersonation for business routes.
     */
    public function handle($request, Closure $next, ...$guards)
    {
        // If this is a business route and admin is impersonating, allow access
        // Also allow admin to access dashboard invoice show/edit (so /dashboard/invoices/* works for admin)
        if (($request->is('dashboard') || $request->is('dashboard/*')) && (empty($guards) || in_array('business', $guards))) {
            // Allow admin to view/edit a specific invoice (e.g. /dashboard/invoices/1) but not list or create
            if (Auth::guard('admin')->check() && $request->is('dashboard/invoices/*') && ctype_digit((string) $request->segment(3))) {
                return $next($request);
            }
            if ($request->session()->has('admin_impersonating_business_id')) {
                $businessId = $request->session()->get('admin_impersonating_business_id');
                $adminId = $request->session()->get('admin_impersonating_admin_id');
                
                // Verify admin is still authenticated and is super admin
                $admin = Auth::guard('admin')->user();
                if ($admin && $admin->id == $adminId && $admin->isSuperAdmin()) {
                    $business = \App\Models\Business::find($businessId);
                    if ($business) {
                        // Login as business if not already logged in as this business
                        if (!Auth::guard('business')->check() || Auth::guard('business')->id() != $businessId) {
                            Auth::guard('business')->login($business);
                        }
                        // Allow the request to proceed without further auth checks
                        return $next($request);
                    } else {
                        // Business not found, clear impersonation
                        $request->session()->forget(['admin_impersonating_business_id', 'admin_impersonating_admin_id']);
                    }
                } else {
                    // Admin session expired, clear impersonation
                    $request->session()->forget(['admin_impersonating_business_id', 'admin_impersonating_admin_id']);
                    if (Auth::guard('business')->check()) {
                        Auth::guard('business')->logout();
                    }
                }
            }
        }

        return parent::handle($request, $next, ...$guards);
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        // Check if admin route
        if ($request->is('admin/*')) {
            return route('admin.login');
        }

        // Check if business route
        if ($request->is('dashboard') || $request->is('dashboard/*')) {
            return route('business.login');
        }

        // My Account (web user)
        if ($request->is('my-account') || $request->is('my-account/*')) {
            return route('account.login');
        }

        // Default to admin login
        return route('admin.login');
    }
}
