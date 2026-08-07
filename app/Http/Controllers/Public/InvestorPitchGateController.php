<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\InvestorPitchAccess;
use App\Models\InvestorPitchPageView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvestorPitchGateController extends Controller
{
    public function lookup(): View
    {
        return view('investor.gate-lookup');
    }

    public function show(string $token): View|RedirectResponse
    {
        $access = InvestorPitchAccess::query()
            ->where('access_token', $token)
            ->where('is_active', true)
            ->first();

        if (! $access) {
            return redirect()
                ->route('investor.gate.lookup')
                ->with('error', 'This investor link is invalid or has been revoked.');
        }

        if (session('investor_pitch_access_id') === $access->id) {
            return redirect()->route('investor.pitch');
        }

        try {
            InvestorPitchPageView::record($access, InvestorPitchPageView::PAGE_GATE, request());
        } catch (\Throwable) {
            // ignore
        }

        return view('investor.gate', compact('access'));
    }

    public function unlock(Request $request, string $token): RedirectResponse
    {
        $access = InvestorPitchAccess::query()
            ->where('access_token', $token)
            ->where('is_active', true)
            ->first();

        if (! $access) {
            return redirect()
                ->route('investor.gate.lookup')
                ->with('error', 'This investor link is invalid or has been revoked.');
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'max:200'],
            'nda_accepted' => ['accepted'],
        ], [
            'nda_accepted.accepted' => 'You must accept the Non-Disclosure Agreement to continue.',
        ]);

        if (! $access->checkPassword($validated['password'])) {
            return back()
                ->withInput($request->except('password'))
                ->withErrors(['password' => 'Incorrect password. Use the password we shared with you.']);
        }

        $access->recordSuccessfulAccess();

        try {
            InvestorPitchPageView::record($access, InvestorPitchPageView::PAGE_UNLOCK, $request);
        } catch (\Throwable) {
            // ignore
        }

        $request->session()->regenerate();
        $request->session()->put([
            'investor_pitch_access_id' => $access->id,
            'investor_pitch_access_name' => $access->name,
            'investor_pitch_access_token' => $access->access_token,
            'investor_pitch_nda_at' => optional($access->fresh()->nda_accepted_at)->toIso8601String(),
        ]);

        return redirect()
            ->intended(route('investor.pitch'))
            ->with('success', 'Welcome, '.$access->name.'. NDA acknowledged — you can view the pitch.');
    }

    public function logout(Request $request): RedirectResponse
    {
        $token = $request->session()->get('investor_pitch_access_token');
        $request->session()->forget([
            'investor_pitch_access_id',
            'investor_pitch_access_name',
            'investor_pitch_access_token',
            'investor_pitch_nda_at',
        ]);
        $request->session()->regenerate();

        if ($token) {
            return redirect()->route('investor.gate', ['token' => $token])
                ->with('success', 'Signed out of the investor pitch.');
        }

        return redirect()->route('investor.gate.lookup');
    }
}
