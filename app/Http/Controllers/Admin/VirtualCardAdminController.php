<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VirtualCardRequest;
use App\Services\Admin\AdminVirtualCardProfitService;
use App\Services\Admin\AdminVirtualCardService;
use App\Services\Consumer\VirtualCardFxPublishService;
use App\Services\Consumer\VirtualCardFxService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VirtualCardAdminController extends Controller
{
    public function __construct(
        private AdminVirtualCardService $cards,
        private AdminVirtualCardProfitService $profit,
    ) {}

    public function index(Request $request): View
    {
        $publish = app(VirtualCardFxPublishService::class);
        $published = $publish->publishedSnapshot();
        if ($published['sell_rate'] === null || $published['buy_rate'] === null) {
            $publish->syncFromMevon();
            $published = $publish->publishedSnapshot();
        }

        $cardFx = app(VirtualCardFxService::class);
        $profitStats = $this->profit->stats($request);

        return view('admin.virtual-cards.index', [
            'cards' => $this->cards->indexQuery($request),
            'stats' => $this->cards->stats(),
            'profitSummary' => $profitStats['summary'],
            'monthlyProfit' => $profitStats['monthly'],
            'publishedRates' => array_merge($published, [
                'fx_available' => $cardFx->isAvailable(),
            ]),
        ]);
    }

    public function refreshRates(): RedirectResponse
    {
        $result = app(VirtualCardFxPublishService::class)->syncFromMevon();

        return redirect()
            ->route('admin.virtual-cards.index')
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function logs(Request $request): View
    {
        return view('admin.virtual-cards.logs', [
            'logs' => $this->cards->logsQuery($request),
        ]);
    }

    public function stats(Request $request): View
    {
        return view('admin.virtual-cards.stats', [
            'stats' => $this->profit->stats($request),
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
