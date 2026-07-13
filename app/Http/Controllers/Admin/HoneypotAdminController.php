<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Security\AdminHoneypotService;
use App\Support\AdminPath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class HoneypotAdminController extends Controller
{
    public function __construct(private AdminHoneypotService $honeypot) {}

    public function index(): View
    {
        return view('admin.honeypot.index', [
            'enabled' => $this->honeypot->isEnabled(),
            'honeypotPath' => '/'.AdminPath::honeypotPrefix(),
            'realPath' => '/'.AdminPath::prefix(),
            'bans' => $this->honeypot->listBans(),
            'recent' => $this->honeypot->recentLogEntries(80),
            'maxHits' => (int) config('admin.honeypot.max_hits', 3),
            'banMinutes' => (int) config('admin.honeypot.ban_minutes', 1440),
        ]);
    }

    public function ban(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ip' => ['required', 'ip'],
            'note' => ['nullable', 'string', 'max:255'],
            'minutes' => ['nullable', 'integer', 'min:1', 'max:525600'],
        ]);

        $ok = $this->honeypot->banIp($validated['ip'], [
            'source' => 'manual',
            'note' => $validated['note'] ?? null,
            'banned_by' => Auth::guard('admin')->id(),
            'hits' => 0,
            'last_path' => 'manual',
        ], isset($validated['minutes']) ? (int) $validated['minutes'] : null);

        return redirect()
            ->route('admin.honeypot.index')
            ->with($ok ? 'success' : 'error', $ok
                ? 'Blocked '.$validated['ip'].'.'
                : 'Could not block that IP.');
    }

    public function unban(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ip' => ['required', 'ip'],
        ]);

        $ok = $this->honeypot->unbanIp($validated['ip']);

        return redirect()
            ->route('admin.honeypot.index')
            ->with($ok ? 'success' : 'error', $ok
                ? 'Unblocked '.$validated['ip'].'.'
                : $validated['ip'].' was not on the block list.');
    }
}
