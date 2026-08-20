<?php

namespace App\Http\Controllers;

use App\Services\Quarantine\QuarantineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuarantineController extends Controller
{
    public function __construct(private QuarantineService $quarantine) {}

    public function status(Request $request): JsonResponse|View
    {
        // Force a fresh evaluation when enabled (may trip lock)
        if ($this->quarantine->isEnabled() && ! $this->quarantine->isLocked()) {
            $reasons = $this->quarantine->evaluateNow();
            if ($reasons !== []) {
                $this->quarantine->trip($reasons);
            }
        }

        $status = $this->quarantine->status();

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'status' => $status['active'] ? 'quarantine' : 'ok',
                'enabled' => $status['enabled'],
                'active' => $status['active'],
                'reasons' => $status['reasons'],
                'tripped_at' => $status['lock']['tripped_at'] ?? null,
            ], $status['active'] ? 503 : 200);
        }

        return view('quarantine.status', [
            'active' => $status['active'],
            'enabled' => $status['enabled'],
            'reasons' => $status['reasons'],
            'lock' => $status['lock'],
        ]);
    }

    public function showUnlock(): View
    {
        return view('quarantine.unlock', [
            'active' => $this->quarantine->isLocked() || $this->quarantine->status()['active'],
        ]);
    }

    public function unlock(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|min:16|max:256',
        ]);

        if (! $this->quarantine->clearWithCode($validated['code'])) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Invalid unlock code.'], 403);
            }

            return back()->withErrors(['code' => 'Invalid unlock code.']);
        }

        // Re-check: if fingerprint still fails, re-trip immediately
        if ($this->quarantine->isEnabled()) {
            $reasons = $this->quarantine->evaluateNow();
            if ($reasons !== []) {
                $this->quarantine->trip($reasons);

                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unlock accepted but fingerprint still failing — quarantine re-armed. Fix DB_HOST / floors first.',
                        'reasons' => $reasons,
                    ], 409);
                }

                return redirect()->route('quarantine.status')
                    ->with('error', 'Code accepted but checks still fail. Fix .error (DB_HOST) and floors, then unlock again.');
            }
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Quarantine cleared.']);
        }

        return redirect('/')->with('success', 'Quarantine cleared. Site is online again.');
    }
}
