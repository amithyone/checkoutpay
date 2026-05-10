<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AllowAdminImpersonation
{
    /**
     * Handle an incoming request.
     *
     * When an allowed admin is impersonating a business, keep the business guard
     * logged in for dashboard routes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if admin is impersonating a business
        if ($request->session()->has('admin_impersonating_business_id')) {
            $businessId = $request->session()->get('admin_impersonating_business_id');
            $adminId = $request->session()->get('admin_impersonating_admin_id');

            // Verify admin is still authenticated and may impersonate
            $admin = Auth::guard('admin')->user();
            if ($admin && $admin->id == $adminId && $admin->canImpersonateBusiness()) {
                // Set the business as the authenticated user for this request
                $business = \App\Models\Business::find($businessId);
                if ($business) {
                    // Login as business if not already logged in as this business
                    if (! Auth::guard('business')->check() || Auth::guard('business')->id() != $businessId) {
                        Auth::guard('business')->login($business);
                    }
                } else {
                    // Business not found, clear impersonation
                    $request->session()->forget(['admin_impersonating_business_id', 'admin_impersonating_admin_id']);
                }
            } else {
                // Admin session expired or invalid, clear impersonation
                $request->session()->forget(['admin_impersonating_business_id', 'admin_impersonating_admin_id']);
                Auth::guard('business')->logout();
            }
        }

        return $next($request);
    }
}
