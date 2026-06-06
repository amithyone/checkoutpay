<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VirtualCardRequest;
use App\Services\Admin\AdminVirtualCardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VirtualCardAdminController extends Controller
{
    public function __construct(
        private AdminVirtualCardService $cards,
    ) {}

    public function index(Request $request): View
    {
        return view('admin.virtual-cards.index', [
            'cards' => $this->cards->indexQuery($request),
            'stats' => $this->cards->stats(),
        ]);
    }

    public function logs(Request $request): View
    {
        return view('admin.virtual-cards.logs', [
            'logs' => $this->cards->logsQuery($request),
        ]);
    }

    public function show(VirtualCardRequest $virtualCardRequest): View
    {
        $ctx = $this->cards->showContext($virtualCardRequest);

        return view('admin.virtual-cards.show', $ctx);
    }

    public function updateNotes(Request $request, VirtualCardRequest $virtualCardRequest): RedirectResponse
    {
        $validated = $request->validate([
            'admin_notes' => 'nullable|string|max:5000',
        ]);

        $result = $this->cards->updateNotes($virtualCardRequest, (string) ($validated['admin_notes'] ?? ''));

        return redirect()
            ->route('admin.virtual-cards.show', $virtualCardRequest)
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function markActive(VirtualCardRequest $virtualCardRequest): RedirectResponse
    {
        $admin = auth('admin')->user();
        $result = $this->cards->markActive($virtualCardRequest, $admin);

        return redirect()
            ->route('admin.virtual-cards.show', $virtualCardRequest)
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function markFailed(Request $request, VirtualCardRequest $virtualCardRequest): RedirectResponse
    {
        $validated = $request->validate([
            'failure_reason' => 'required|string|max:500',
        ]);

        $admin = auth('admin')->user();
        $result = $this->cards->markFailed(
            $virtualCardRequest,
            $admin,
            (string) $validated['failure_reason']
        );

        return redirect()
            ->route('admin.virtual-cards.show', $virtualCardRequest)
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function retry(VirtualCardRequest $virtualCardRequest): RedirectResponse
    {
        $result = $this->cards->retryProvider($virtualCardRequest);

        return redirect()
            ->route('admin.virtual-cards.show', $virtualCardRequest)
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function refundFee(VirtualCardRequest $virtualCardRequest): RedirectResponse
    {
        $admin = auth('admin')->user();
        $result = $this->cards->refundFee($virtualCardRequest, $admin);

        return redirect()
            ->route('admin.virtual-cards.show', $virtualCardRequest)
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }
}
