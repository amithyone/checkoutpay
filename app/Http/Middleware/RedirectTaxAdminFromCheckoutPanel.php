<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectTaxAdminFromCheckoutPanel
{
    /**
     * Tax-role admins only use the NigTax portal; keep them out of the payment gateway admin UI.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = auth('admin')->user();

        if ($admin instanceof Admin && $admin->role === Admin::ROLE_TAX) {
            $url = rtrim(config('app.tax_admin_url', 'https://nigtax.com/admin'), '/');

            return redirect()->away($url);
        }

        return $next($request);
    }
}
