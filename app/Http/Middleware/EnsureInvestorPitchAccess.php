<?php

namespace App\Http\Middleware;

use App\Models\InvestorPitchAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInvestorPitchAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $accessId = $request->session()->get('investor_pitch_access_id');
        if (! $accessId) {
            return redirect()
                ->guest(route('investor.gate.lookup'))
                ->with('error', 'Open your personal investor link and enter your password to continue.');
        }

        $access = InvestorPitchAccess::query()
            ->whereKey($accessId)
            ->where('is_active', true)
            ->first();

        if (! $access) {
            $request->session()->forget([
                'investor_pitch_access_id',
                'investor_pitch_access_name',
                'investor_pitch_access_token',
                'investor_pitch_nda_at',
            ]);

            return redirect()->route('investor.gate.lookup')
                ->with('error', 'Your investor access is no longer active. Contact Checkout for a new link.');
        }

        $request->attributes->set('investorPitchAccess', $access);
        view()->share('investorPitchViewer', $access);

        $response = $next($request);

        if ($request->isMethod('GET') && $response->getStatusCode() < 400) {
            $pageKey = match (true) {
                $request->routeIs('investor.summary') => \App\Models\InvestorPitchPageView::PAGE_SUMMARY,
                $request->routeIs('investor.pitch') => \App\Models\InvestorPitchPageView::PAGE_PITCH,
                default => null,
            };
            if ($pageKey) {
                try {
                    \App\Models\InvestorPitchPageView::record($access, $pageKey, $request);
                } catch (\Throwable) {
                    // Never block pitch viewing on analytics write failure.
                }
            }
        }

        return $response;
    }
}
